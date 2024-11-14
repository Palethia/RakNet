<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\connection;

use Laith98Dev\palethia\network\raknet\CongestionManager;
use Laith98Dev\palethia\network\raknet\DatagramBuilder;
use Laith98Dev\palethia\network\raknet\DsHeap;
use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\protocol\ds\RangeList;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectedPing;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectedPong;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectionRequest;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectionRequestAccepted;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\AckedDatagram;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\Datagram;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\DatagramIdentifier;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\NackedDatagram;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Arrangement;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Capsule;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Reliability;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Segment;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\ValidDatagram;
use Laith98Dev\palethia\network\raknet\protocol\packet\DisconnectionNotification;
use Laith98Dev\palethia\network\raknet\protocol\packet\NewIncomingConnection;
use Laith98Dev\palethia\network\raknet\protocol\packet\RakPacket;
use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakBinaryStream;
use Laith98Dev\palethia\network\raknet\RakInfo;
use Laith98Dev\palethia\network\raknet\RakPeer;

class Connection
{

	public ConnectionState $state = ConnectionState::NOT_CONNECTED;

	private CongestionManager $congestion_manager;

	private RangeList $ack_queue;
	private RangeList $nack_queue;
	private array $dgram_queue = [];

	private array $receiver_reliable_packets = [];

	private array $retransmission_queue = [];
	private array $reassembly_queue = [];

	private array $arranged_write_index;
	private array $sequenced_write_index;

	private array $arranged_read_index;
	private array $arranged_heaps;
	private array $heap_index_offsets;
	private array $highest_sequenced_read_index;

	private int $receiver_range_number = 0;

	private int $sender_range_number = 0;

	private int $sender_last_segment_id = 0;

	private int $sender_reliable_index = 0;

	private int $receiver_reliable_base_index = 0;

	private int $max_datagram_size_excluding_header_bytes = 0;

	private int $dgrams_sent_so_far = 0;
	private int $max_dgram_send_count = 0;
	private int $max_dgram_send_count_adjustment = 0;
	private float $last_dgram_send_tick = 0;

	private bool $sending_dgrams_slowed = false;

	public array $received_capsuled_packets = [];

	public function __construct(
		public readonly InternetAddress $address,
		public readonly int $mtu_size,
		public readonly int $guid,
		public RakPeer $peer,
		public ?ConnectionInfo $connection_info = null
	) {
		$this->congestion_manager = new CongestionManager($mtu_size);

		$this->ack_queue = new RangeList();
		$this->nack_queue = new RangeList();

		$this->arranged_write_index = $this->createSizedArray();
		$this->sequenced_write_index = $this->createSizedArray();

		$this->arranged_read_index = $this->createSizedArray();
		$this->arranged_heaps = $this->createSizedArray(new DsHeap(false));
		$this->heap_index_offsets = $this->createSizedArray();
		$this->highest_sequenced_read_index = $this->createSizedArray();

		$this->max_datagram_size_excluding_header_bytes = $mtu_size - Datagram::getHeaderSize();

		[$this->max_dgram_send_count, $this->max_dgram_send_count_adjustment] = $this->congestion_manager->calcMaxDgramSendCount();
	}

	private function createSizedArray(mixed $value = 0, int $max = RakInfo::NUMBER_OF_ARRANGED_STREAMS): array
	{
		return array_fill(0, $max, $value);
	}

	private function createArrangement(Capsule &$capsule, int $arranged_channel = 0): Arrangement
	{
		$arrangement = new Arrangement;
		$arrangement->channel = $arranged_channel;

		if ($capsule->reliability->isSequenced()) {
			$arrangement->index = $this->arranged_write_index[$arranged_channel];
			$capsule->sequenced_index = $this->sequenced_write_index[$arranged_channel]++;
		} else {
			$arrangement->index = $this->arranged_write_index[$arranged_channel]++;
			$this->sequenced_write_index[$arranged_channel] = 0;
		}

		return $arrangement;
	}

	private function createCapsule(Reliability $reliability, string $buffer, bool $include_arrangement = true, int $arranged_channel = 0): Capsule
	{
		$capsule = new Capsule();
		$capsule->reliability = $reliability;

		if ($include_arrangement && $reliability->isSequencedOrArranged()) {
			$capsule->arrangement = $this->createArrangement($capsule, $arranged_channel);
		}

		if ($capsule->reliability->isReliable()) {
			$capsule->reliable_index = $this->sender_reliable_index++;
		}

		$capsule->stream = new RakBinaryStream($buffer);

		return $capsule;
	}

	public function sendPacket(string $buffer, Reliability $reliability, int $arranged_channel = 0): void
	{
		$this->enqueueCapsule($this->createCapsule($reliability, $buffer, true, $arranged_channel));
		$this->sendDgrams();
	}

	public function sendOnlineRakPacket(RakPacket $packet, Reliability $reliability = Reliability::UNRELIABLE): void
	{
		$packet->serialize();

		$identifier = DatagramBuilder::createDatagram($this->sender_range_number++, [$this->createCapsule($reliability, $packet->getBuffer(), false)]);
		$identifier->serialize();

		$this->sendRawPacket($identifier->identifing_datagram->getBuffer());
	}

	public function sendRawPacket(string $buffer): void
	{
		$this->peer->socket->streamToSocket($buffer, $this->address);
	}

	public function tick(): void
	{
		$this->sendAcks();
		$this->sendNacks();
		$this->sendDgrams();
	}

	public function enqueueCapsule(Capsule $capsule): void
	{
		if ($capsule->segment !== null) return;

		$stream_buffer = $capsule->stream->getBuffer();
		$stream_buffer_size = strlen($stream_buffer);

		if ($stream_buffer_size > ($max_block_size = $this->max_datagram_size_excluding_header_bytes - $capsule->getSize())) {
			$segments_size = intval(ceil($stream_buffer_size / $max_block_size));

			$this->sender_last_segment_id &= 0xffff;

			for ($current_segment_index = 0; $current_segment_index < $segments_size; ++$current_segment_index) {
				$start_offset = $current_segment_index * $max_block_size;
				$bytes_to_send = $stream_buffer_size - $start_offset;

				if ($bytes_to_send > $max_block_size) {
					$bytes_to_send = $max_block_size;
				}

				$end_offset = $bytes_to_send !== $max_block_size ? $stream_buffer_size - $current_segment_index * $max_block_size : $bytes_to_send;

				$capsule_segment = new Segment;
				$capsule_segment->size = $segments_size;
				$capsule_segment->id = $this->sender_last_segment_id;
				$capsule_segment->index = $current_segment_index;

				$segmented_capsule_part = new Capsule();
				$segmented_capsule_part->reliability = $capsule->reliability->convertToReliableIfUnreliable();
				if ($segmented_capsule_part->reliability->isReliable()) {
					$segmented_capsule_part->reliable_index = $this->sender_reliable_index++;
				}
				if ($segmented_capsule_part->reliability->isSequenced()) {
					$segmented_capsule_part->sequenced_index = $capsule->sequenced_index;
				}
				$segmented_capsule_part->arrangement = $capsule->arrangement;
				$segmented_capsule_part->segment = $capsule_segment;
				$segmented_capsule_part->stream = new RakBinaryStream(substr($stream_buffer, $start_offset, $end_offset));

				$this->dgram_queue[$this->sender_range_number] = $segmented_capsule_part;
				$this->sendDgrams(true, $current_segment_index + 1 === $segments_size);
			}

			++$this->sender_last_segment_id;
		} else {
			$this->dgram_queue[$this->sender_range_number] = $capsule;
		}
	}

	public function handleDatagram(DatagramIdentifier $identifier): void
	{
		$datagram = $identifier->identifing_datagram;

		if ($datagram instanceof ValidDatagram) {
			$skipped_range_number_count = $this->congestion_manager->calcSkippedRangeNumberCount($this->receiver_range_number = $datagram->range_number);

			array_splice($this->nack_queue->nodes, $this->receiver_range_number, 1);

			$this->ack_queue->insert($this->receiver_range_number);

			if ($skipped_range_number_count > 0) {
				for ($skipped_range_number_offset = $skipped_range_number_count; $skipped_range_number_offset > 0; $skipped_range_number_offset--) {
					$this->nack_queue->insert($this->receiver_range_number - $skipped_range_number_offset);
				}
				return;
			} else if ($skipped_range_number_count === -1) { // nat related
				return;
			}

			for ($i = 0; $i < count($datagram->capsules); ++$i) {
				$this->handleCapsule($datagram->capsules[$i]);
			}
		} else if ($datagram instanceof AckedDatagram) {
			$this->handleAck($datagram);
		} else if ($datagram instanceof NackedDatagram) {
			$this->handleNack($datagram);
		}
	}

	protected function handleCapsule(Capsule $capsule): void
	{
		if ($capsule->segment !== null) {
			$this->handleSegmentedCapsule($capsule);
			return;
		}

		if ($capsule->reliability->isSequencedOrArranged(false)) {
			$arrangement_channel = $capsule->arrangement?->channel;

			if ($arrangement_channel >= RakInfo::NUMBER_OF_ARRANGED_STREAMS) { // corrupt arrangement channel
				return;
			}
		}

		if ($capsule->reliability->isReliableOrInSequence()) {
			$hole_count = $this->receiver_range_number - $this->receiver_reliable_base_index;

			if ($hole_count === 0) {
				++$this->receiver_reliable_base_index;
			} else if ($hole_count > 0x7fffff) { // duplicated packet
				return;
			} else {
				if (!isset($this->receiver_reliable_packets[$hole_count])) {
					$this->receiver_reliable_packets[$hole_count] = true;
				} else { // duplicated packet
					return;
				}
			}

			if ($hole_count > 0xf4240) { // hole count is too high
				var_dump("Hole count too high");
				return;
			}

			for ($i = $this->receiver_reliable_base_index; $i < $hole_count & 0xffffffff; ++$i) {
				if (!isset($this->receiver_reliable_packets[$i])) {
					$this->receiver_reliable_packets[$i] = false;
				}
			}

			while (isset($this->receiver_reliable_packets[$this->receiver_reliable_base_index])
				&& $this->receiver_reliable_packets[$this->receiver_reliable_base_index] === false) {
				unset($this->receiver_reliable_packets[$this->receiver_reliable_base_index]);
				++$this->receiver_reliable_base_index;
			}
		}

		if ($capsule->reliability->isSequencedOrArranged(false) && $capsule->arrangement !== null) {
			$arrangement = $capsule->arrangement;
			$channel = $arrangement->channel;
			$current_index = $arrangement->index;
			$expected_index = $this->arranged_read_index[$channel];

			if ($current_index === $expected_index) {
				if ($capsule->reliability->isSequenced()) {
					if (!$this->isOlderArrangedPacket($capsule->sequenced_index, $this->highest_sequenced_read_index[$channel])) {
						$this->highest_sequenced_read_index[$channel] = $capsule->sequenced_index + 1;
					} else { // received sequenced capsule with lower index than known
						var_dump("received seq capsule with lower index than known");
						return;
					}
				} else {
					$this->arranged_read_index[$channel]++;
					$this->highest_sequenced_read_index[$channel] = 0;

					while (count($this->arranged_heaps[$channel]) > 0 && $this->arranged_heaps[$channel]->peek()?->arrangement->index === $this->arranged_read_index[$channel]) {
						$capsule_heap = $this->arranged_heaps[$channel]->pop(0);
						$arrangement_heap = $capsule_heap->arrangement;

						if ($arrangement_heap === null) {
							var_dump("arrangement heap is null");
							return;
						}

						$arrangement_channel_heap = $arrangement_heap->channel;

						var_dump("OUT");

						if ($capsule_heap->reliability === Reliability::RELIABLE) {
							$this->arranged_read_index[$arrangement_channel_heap]++;
						} else {
							$this->highest_sequenced_read_index[$arrangement_channel_heap] = $capsule->sequenced_index;
						}
					}
				}
			} else if (!$this->isOlderArrangedPacket($current_index, $expected_index)) {
				if ($this->arranged_heaps[$channel]->count() === 0) {
					$this->heap_index_offsets[$channel] = $expected_index;
				}

				$arranged_hole_count = $current_index - $this->heap_index_offsets[$channel];
				$weight = $arranged_hole_count * 0x100000;

				if ($capsule->reliability->isSequenced()) {
					$weight += $capsule->sequenced_index;
				} else {
					$weight += (0x100000 - 1);
				}

				$weight &= 0xffffffffffffffff;

				var_dump("is older or arraned packet");
				$this->arranged_heaps[$channel]->push($weight, $capsule);
			} else { // received out of arrangement packet
				return;
			}
		}

		$this->handleCapsuledPacket($capsule->stream);
	}

	public function handleCapsuledPacket(RakBinaryStream $stream): void
	{
		if ($this->state === ConnectionState::NOT_CONNECTED) {
			$this->state = ConnectionState::CONNECTING;
		} else if (
			$this->state === ConnectionState::DISCONNECTED ||
			$this->state === ConnectionState::INCOMING_CONNECTION_INVALID_PORT ||
			$this->state === ConnectionState::ACCEPTED_CONNECTION_INVALID_PORT
		) {
			var_dump("HAS BEEN DISCONNECTED || INVALID PORT - shutdown & exitting (temp solution)");
			$this->peer->shutdown();
			exit;
			return;
		}

		$actual_id = $stream->getByte();
		$id = RakPacketId::tryFrom($actual_id) ?? RakPacketId::INVALID;

		$stream_buffer = $stream->getBuffer();

		if ($this->peer->net_handler === null) {
			$this->received_capsuled_packets[] = $stream_buffer;
		}

		if ($id === RakPacketId::DISCONNECTION_NOTIFICATION) {
			$this->state = ConnectionState::DISCONNECTED;

			$this->peer->connection_manager->remove($this);

			$this->peer->net_handler?->handleDisconnection($this->address);
			return;
		}

		if ($this->state !== ConnectionState::CONNECTED) {
			switch ($id) {
				case RakPacketId::CONNECTION_REQUEST:
					$packet = new ConnectionRequest($stream_buffer);
					$packet->deserialize();

					$this->peer->upstream->connectionRequestAccepted($this->peer->connection_manager->getIndex($this->address), $packet->client_send_time, $this->address);
					break;
				case RakPacketId::CONNECTION_REQUEST_ACCEPTED:
					$packet = new ConnectionRequestAccepted($stream_buffer);
					$packet->deserialize();

					$this->peer->is_minecraft_protocol = $packet->is_minecraft_protocol;

					$this->peer->upstream->newIncomingConnection($packet->server_send_time, $this->address);

					$this->peer->net_handler?->handleConnection($this);

					$this->state = ConnectionState::CONNECTED;

					$this->peer->upstream->ping($this->address);
					break;
				case RakPacketId::NEW_INCOMING_CONNECTION:
					$packet = new NewIncomingConnection($stream_buffer);
					$packet->deserialize();

					if (($server_port = $packet->server_address->port) !== $actual_server_port = $this->peer->address->port) {
						$this->state = ConnectionState::INCOMING_CONNECTION_INVALID_PORT;
						var_dump("new incoming connection invalid port $server_port, the actual: $actual_server_port");
						return;
					}

					$this->peer->is_minecraft_protocol = $packet->is_minecraft_protocol;

					$this->peer->net_handler?->handleConnection($this);

					$this->state = ConnectionState::CONNECTED;

					$this->peer->upstream->pong($packet->client_send_time, $this->address);
					break;
			}
		} else {
			if ($id === RakPacketId::CONNECTED_PING) {
				$packet = new ConnectedPing($stream_buffer);
				$packet->deserialize();

				$this->peer->upstream->pong($packet->client_send_time, $this->address);
			} else if ($id === RakPacketId::CONNECTED_PONG) {
				$packet = new ConnectedPong($stream_buffer);
				$packet->deserialize();

				$this->peer->upstream->ping($this->address);
			} else if ($id === RakPacketId::INVALID && $actual_id > RakPacketId::USER_PACKET_ENUM->value) {
				$stream->rewind();

				$this->peer->net_handler?->handlePacket($stream, $this->address);
			}
		}
	}

	private function isOlderArrangedPacket(int $new_packet_arranged_index, int  $expected_packet_arranged_index)
	{
		$half_span = 0x7fffffff;

		return ($expected_packet_arranged_index > $half_span
			? $new_packet_arranged_index >= $expected_packet_arranged_index - $half_span + 1 && $new_packet_arranged_index < $expected_packet_arranged_index
			: $new_packet_arranged_index >= $expected_packet_arranged_index - ($half_span + 1) || $new_packet_arranged_index < $expected_packet_arranged_index);
	}

	protected function handleSegmentedCapsule(Capsule $capsule): void
	{
		$segment = $capsule->segment;
		$segment_size = $segment->size;
		$segment_id = $segment?->id;
		$segment_index = $segment?->index;

		if (!isset($this->reassembly_queue[$segment_id])) {
			$this->reassembly_queue[$segment_id] = [];
		}

		if (!isset($this->reassembly_queue[$segment_id][$segment_index])) {
			$this->reassembly_queue[$segment_id][$segment_index] = $capsule->stream->getBuffer();
		}

		if (($reassembling_size = count($this->reassembly_queue[$segment_id])) === $segment_size) {
			$reassembled_capsule = new Capsule();
			$reassembled_capsule->reliability = $capsule->reliability;
			if ($reassembled_capsule->reliability->isReliable()) {
				$reassembled_capsule->reliable_index = $capsule->reliable_index;
				var_dump("handling segmented reliable");
			}
			if ($capsule->reliability->isSequenced()) {
				$reassembled_capsule->sequenced_index = $capsule->sequenced_index;
			}
			$reassembled_capsule->arrangement = $capsule->arrangement;
			$reassembled_capsule->stream = new RakBinaryStream();

			for ($i = 0; $i < $reassembling_size; ++$i) {
				$reassembled_capsule->stream->put($this->reassembly_queue[$segment_id][$i]);
			}

			unset($this->reassembly_queue[$segment_id]);
			$this->handleCapsule($reassembled_capsule);
		}
	}

	private function sendAcks(): void
	{
		if (count($this->ack_queue->nodes) < 1) {
			return;
		}

		$identifier = DatagramBuilder::createAcknowledge($this->ack_queue);
		$identifier->serialize();

		$this->sendRawPacket($identifier->identifing_datagram->getBuffer());

		$this->ack_queue->nodes = [];
	}

	private function sendNacks(): void
	{
		if (count($this->nack_queue->nodes) < 1) {
			return;
		}

		$identifier = DatagramBuilder::createAcknowledge($this->nack_queue, true);
		$identifier->serialize();

		$this->sendRawPacket($identifier->identifing_datagram->getBuffer());

		$this->nack_queue->nodes = [];
	}

	private function sendDgrams(bool $immediate = false, bool $requires_tick_reset = false): void
	{
		if (count($this->dgram_queue) < 1) {
			return;
		}

		$capsules = $immediate ? $this->dgram_queue : [];

		if ($this->dgrams_sent_so_far >= $this->max_dgram_send_count || $this->sending_dgrams_slowed) {
			$index = 0;

			while ($index <= $this->dgrams_sent_so_far) {
				if (!$this->last_dgram_send_tick !== 0 && (microtime(true) - $this->last_dgram_send_tick) * 1000000 < $this->max_dgram_send_count_adjustment) {
					continue;
				}

				$this->last_dgram_send_tick = microtime(true);
				++$index;
			}

			if ($requires_tick_reset) {
				$this->dgrams_sent_so_far = 0;
				$this->last_dgram_send_tick = 0;
				unset($index);
			}
		}

		if (!$immediate) {
			$sender_datagram_capsule_sizes = array_sum(array_map(static fn (Capsule $capsule) => $capsule->getSize(), array_values($this->dgram_queue)));

			if ($sender_datagram_capsule_sizes > 0) {
				foreach ($this->dgram_queue as $capsule) {
					$current_size = $capsule->getSize();

					$sender_datagram_capsule_sizes += $current_size;

					$next_packet_size = $capsule->getSize() + strlen($capsule->stream->getBuffer());
					if (($sender_datagram_capsule_sizes + $next_packet_size) < $this->max_datagram_size_excluding_header_bytes) {
						$capsules[] = $capsule;
					} else {
						var_dump("packet bigger than mtu");
						continue;
					}
				}
			}
		}

		if ($immediate ? true : !empty($capsules)) {
			$this->dgram_queue = [];

			$identifier = DatagramBuilder::createDatagram($this->sender_range_number++, $capsules);
			unset($capsules);

			$this->retransmission_queue[$this->sender_range_number] = $identifier;

			$identifier->serialize();
			$this->sendRawPacket($identifier->identifing_datagram->getBuffer());

			++$this->dgrams_sent_so_far;
		}
	}

	public function handleAck(AckedDatagram $value): void
	{
		foreach ($value->range_list->nodes as $node) {
			if ($node->min > $node->max) {
				continue;
			}

			for ($range_number = $node->min; $range_number >= $node->min && $range_number <= $node->max; ++$range_number) {
				if (isset($this->retransmission_queue[$range_number])) {
					unset($this->retransmission_queue[$range_number]);
				}
			}
		}
	}

	public function handleNack(NackedDatagram $value): void
	{
		foreach ($value->range_list->nodes as $node) {
			if ($node->min > $node->max) {
				continue;
			}

			$range_number = $node->min;
			while ($range_number >= $node->min && $range_number <= $node->max) {
				if (isset($this->retransmission_queue[$range_number])) {
					if (!$this->sending_dgrams_slowed) $this->sending_dgrams_slowed = true;

					$this->dgram_queue[$range_number] = $this->retransmission_queue[$range_number];

					$this->sendDgrams(true, $range_number >= $node->max);
				}

				++$range_number;
			}

			if ($this->sending_dgrams_slowed) $this->sending_dgrams_slowed = false;
		}
	}

	public function disconnect(): void
	{
		$this->sendOnlineRakPacket(new DisconnectionNotification(), Reliability::RELIABLE);
	}
}
