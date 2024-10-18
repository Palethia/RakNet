<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol;

use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\protocol\packet\AlreadyConnected;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectedPing;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectedPong;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectionRequest;
use Laith98Dev\palethia\network\raknet\protocol\packet\ConnectionRequestAccepted;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Reliability;
use Laith98Dev\palethia\network\raknet\protocol\packet\NewIncomingConnection;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionReplyOne;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionReplyTwo;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionRequestOne;
use Laith98Dev\palethia\network\raknet\protocol\packet\OpenConnectionRequestTwo;
use Laith98Dev\palethia\network\raknet\protocol\packet\RakPacket;
use Laith98Dev\palethia\network\raknet\protocol\packet\UnconnectedPing;
use Laith98Dev\palethia\network\raknet\protocol\packet\UnconnectedPong;
use Laith98Dev\palethia\network\raknet\RakInfo;
use Laith98Dev\palethia\network\raknet\RakPeer;
use RuntimeException;

class Upstream
{

	public function __construct(
		private RakPeer $peer
	) {
		// NOOP
	}

	public function unconnectedPing(InternetAddress $address): void
	{
		$packet = new UnconnectedPing();
		$packet->client_send_time = $this->peer->getTime();
		$packet->client_guid = $this->peer->guid;
		$this->sendPacket($packet, $address);
	}

	public function unconnectedPong(InternetAddress $address): void
	{
		$packet = new UnconnectedPong();
		$packet->server_guid = $this->peer->guid;
		$packet->server_send_time = $this->peer->getTime();
		$packet->response_data = $this->peer->response_data;
		$this->sendPacket($packet, $address);
	}

	public function openConnectionRequest(int $number, InternetAddress $address, int $mtu_size = RakInfo::MAXIMUM_MTU_SIZE, array $security_info = []): void
	{
		$packet = null;

		if ($number === 1) {
			$packet = new OpenConnectionRequestOne();
			$packet->protocol_version = RakInfo::PROTOCOL_VERSION;
			$packet->mtu_size = $mtu_size;
		} else if ($number === 2) {
			$packet = new OpenConnectionRequestTwo();
			if (!empty($security_info)) {
				$packet->reply_one_has_security = true;
				$packet->client_security["cookie"] = $security_info["cookie"];
			}
			$packet->server_address = $this->peer->address;
			$packet->mtu_size = $mtu_size;
			$packet->client_guid = $this->peer->guid;
		} else {
			throw new RuntimeException("Invalid open connection request number $number");
		}

		if ($packet !== null) {
			$this->sendPacket($packet, $address);
		}
	}

	public function openConnectionReply(int $number, int $mtu_size, InternetAddress $address): void
	{
		$packet = null;

		if ($number === 1) {
			$packet = new OpenConnectionReplyOne();
			$packet->server_guid = $this->peer->guid;
			$packet->mtu_size = $mtu_size;
		} else if ($number === 2) {
			$packet = new OpenConnectionReplyTwo();
			$packet->server_guid = $this->peer->guid;
			$packet->client_address = $address;
			$packet->mtu_size = $mtu_size;
		} else {
			throw new RuntimeException("Invalid open connection reply number $number");
		}

		if ($packet !== null) {
			$this->sendPacket($packet, $address);
		}
	}

	public function alreadyConnected(int $client_guid, InternetAddress $address): void
	{
		$packet = new AlreadyConnected();
		$packet->client_guid = $client_guid;
		$this->sendPacket($packet, $address);
	}

	public function connectionRequest(InternetAddress $address): void
	{
		$packet = new ConnectionRequest;
		$packet->client_guid = $this->peer->guid;
		$packet->client_send_time = $this->peer->getTime();

		$this->peer->connection_manager->get(strval($address))->sendOnlineRakPacket($packet, Reliability::RELIABLE);
	}

	public function connectionRequestAccepted(int $client_index, int $client_send_time, InternetAddress $address): void
	{
		$packet = new ConnectionRequestAccepted;
		$packet->client_address = $address;
		$packet->client_index = $client_index;
		$packet->server_machine_addresses = (array)$this->peer->machine_addresses;
		$packet->is_minecraft_protocol = $this->peer->is_minecraft_protocol;
		$packet->client_send_time = $client_send_time;
		$packet->server_send_time = $this->peer->getTime();

		$this->peer->connection_manager->get(strval($address))->sendOnlineRakPacket($packet, Reliability::RELIABLE);
	}

	public function newIncomingConnection(int $server_send_time, InternetAddress $address): void
	{
		$packet = new NewIncomingConnection;
		$packet->server_address = $this->peer->address;
		$packet->is_minecraft_protocol = $this->peer->is_minecraft_protocol;
		$packet->client_machine_addresses = (array)$this->peer->machine_addresses;
		$packet->client_send_time = $this->peer->getTime();
		$packet->server_send_time = $server_send_time;

		$this->peer->connection_manager->get(strval($address))->sendOnlineRakPacket($packet, Reliability::RELIABLE);
	}

	public function ping(InternetAddress $address): void
	{
		$packet = new ConnectedPing;
		$packet->client_send_time = $this->peer->getTime();

		$this->peer->connection_manager->get(strval($address))->sendOnlineRakPacket($packet, Reliability::UNRELIABLE);
	}

	public function pong(int $client_send_time, InternetAddress $address): void
	{
		$packet = new ConnectedPong;
		$packet->client_send_time = $client_send_time;
		$packet->server_send_time = $this->peer->getTime();

		$this->peer->connection_manager->get(strval($address))->sendOnlineRakPacket($packet, Reliability::UNRELIABLE);
	}

	public function sendPacket(RakPacket $packet, InternetAddress $address): void
	{
		$packet->serialize();
		$this->peer->socket->streamToSocket($packet->getBuffer(), $address);
	}
}
