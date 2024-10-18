<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol;

use Laith98Dev\palethia\console\Logger;
use Laith98Dev\palethia\network\raknet\connection\Connection;
use Laith98Dev\palethia\network\raknet\connection\ConnectionInfo;
use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\DatagramIdentifier;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\InvalidDatagram;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionReplyOne;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionReplyTwo;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionRequestOne;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionRequestTwo;
use Laith98Dev\palethia\network\raknet\RakBinaryStream;
use Laith98Dev\palethia\network\raknet\RakClient;
use Laith98Dev\palethia\network\raknet\RakPeer;

class Downstream
{

	public function __construct(
		private RakPeer $peer
	) {
		// NOOP
	}

	public function handle(RakBinaryStream $stream, InternetAddress $address): void
	{
		$actual_id = $stream->getByte();
		$id = RakPacketId::tryFrom($actual_id) ?? RakPacketId::INVALID;
		$stream_buffer = $stream->getBuffer();

		switch ($id) {
			case RakPacketId::UNCONNECTED_PING:
				$this->peer->upstream->unconnectedPong($address);
				break;
			case RakPacketId::UNCONNECTED_PONG:
				// do some calculation for Palethia::MTU_SIZES

				$this->peer->upstream->openConnectionRequest(1, $address);
				break;
			case RakPacketId::OPEN_CONNECTION_REQUEST_ONE:
				// I have put this back here beceause i have a pocketmine private fork that has messed up
				// raklib that once you don't show the behaviour of a real client you get banned immediately
				// no matter what you do and this is just the start of it but
				// palethia already bypasses everything that serves as protection
				// and also lifeboat resends this packet back to us too so they can also detect
				// so this is the way to bypass those things
				if ($this->peer instanceof RakClient) {
					return;
				}

				$packet = new OpenConnectionRequestOne($stream_buffer);
				$packet->deserialize();

				$this->peer->upstream->openConnectionReply(1, $packet->mtu_size, $address);
				break;
			case RakPacketId::OPEN_CONNECTION_REPLY_ONE:
				$packet = new OpenConnectionReplyOne($stream_buffer);
				$packet->deserialize();

				$this->peer->upstream->openConnectionRequest(2, $address, $packet->mtu_size, $packet->server_security);
				break;
			case RakPacketId::INCOMPATIBLE_PROTOCOL_VERSION:
				Logger::error("Incompatible protocol version");
				$this->peer->shutdown();
				break;
			case RakPacketId::OPEN_CONNECTION_REQUEST_TWO:
				$packet = new OpenConnectionRequestTwo($stream_buffer);
				$packet->deserialize();

				$client_guid = $packet->client_guid;
				$mtu_size = $packet->mtu_size;

				$connection_manager = $this->peer->connection_manager;

				$has_internal_conn = $connection_manager->get(strval($address)) !== null;
				$has_internal_conn_from_guid = $connection_manager->get($client_guid) !== null;

				$connection_outcome = 0;

				if (($has_internal_conn & $has_internal_conn_from_guid) === 0) {
					if (!$has_internal_conn && !$has_internal_conn_from_guid) {
						$connection_outcome = 1;
					} else {
						$connection_outcome = 2;
					}
				} else {
					if (!$has_internal_conn && $has_internal_conn_from_guid) {
						$connection_outcome = 3;
					} else if ($has_internal_conn && !$has_internal_conn_from_guid) {
						$connection_outcome = 4;
					}
				}

				if ($connection_outcome === 1) {
					$this->peer->connection_manager->add(new Connection($address, $mtu_size, $client_guid, $this->peer));

					$this->peer->upstream->openConnectionReply(2, $mtu_size, $address);
				} else if ($connection_outcome !== 0) {
					$this->peer->upstream->alreadyConnected($client_guid, $address);
				}
				break;
			case RakPacketId::OPEN_CONNECTION_REPLY_TWO:
				$packet = new OpenConnectionReplyTwo($stream_buffer);
				$packet->deserialize();

				$connection_info = new ConnectionInfo;
				$connection_info->client_address = $packet->client_address;

				$this->peer->connection_manager->add(new Connection($address, $packet->mtu_size, $this->peer->guid, $this->peer, $connection_info));

				$this->peer->upstream->connectionRequest($address);
				break;
			case RakPacketId::CONNECTION_ATTEMPT_FAILED:
				Logger::error("Connection attempt failed");
				$this->peer->shutdown();
				break;
			default:
				$identifier = new DatagramIdentifier($stream_buffer);
				$identifier->deserialize();

				if ($identifier instanceof InvalidDatagram) return;

				$connection = $this->peer->connection_manager->get(strval($address));

				if ($connection !== null) $connection->handleDatagram($identifier);
				break;
		}
	}
}
