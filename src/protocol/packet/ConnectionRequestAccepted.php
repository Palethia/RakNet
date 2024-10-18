<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakInfo;

class ConnectionRequestAccepted extends RakPacket
{
	public const PACKET_ID = RakPacketId::CONNECTION_REQUEST_ACCEPTED;

	public InternetAddress $client_address;
	public int $client_index;
	public bool $is_minecraft_protocol = false;
	public array $server_machine_addresses = [];
	public int $client_send_time;
	public int $server_send_time;

	public function serializeBody(): void
	{
		$this->writeAddress($this->client_address);
		$this->putShort($this->client_index);
		$first_address_version = $this->server_machine_addresses[0]->version;
		for ($i = 0; $i < RakInfo::MAX_NUMBER_OF_MACHINE_ADDRESSES * ($this->is_minecraft_protocol ? 2 : 1); ++$i) {
			$this->writeAddress($this->server_machine_addresses[$i] ?? InternetAddress::random($first_address_version));
		}
		$this->putLong($this->client_send_time);
		$this->putLong($this->server_send_time);
	}

	public function deserializeBody(): void
	{
		$this->client_address = $this->readAddress();
		$this->client_index = $this->getShort();
		$this->is_minecraft_protocol = strlen($this->getBuffer()) - 1 - (1 + ($this->client_address->version === 4 ? 4 + 2 : 2 + 2 + 4 + 8 * 2 + 4)) === 248;
		for ($i = 0; $i < RakInfo::MAX_NUMBER_OF_MACHINE_ADDRESSES * ($this->is_minecraft_protocol ? 2 : 1); ++$i) {
			$this->server_machine_addresses[] = $this->readAddress();
		}
		$this->client_send_time = $this->getLong();
		$this->server_send_time = $this->getLong();
	}
}
