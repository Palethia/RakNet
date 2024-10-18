<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakInfo;

class NewIncomingConnection extends RakPacket
{
	public const PACKET_ID = RakPacketId::NEW_INCOMING_CONNECTION;

	public InternetAddress $server_address;
	public bool $is_minecraft_protocol = false; 
	/** @var InternetAddress[] */
	public array $client_machine_addresses = [];
	public int $client_send_time;
	public int $server_send_time;

	public function serializeBody(): void
	{
		$this->writeAddress($this->server_address);
		$first_address_version = $this->client_machine_addresses[0]->version;
		for ($i = 0; $i < RakInfo::MAX_NUMBER_OF_MACHINE_ADDRESSES * ($this->is_minecraft_protocol ? 2 : 1); ++$i) {
			$this->writeAddress($this->client_machine_addresses[$i] ?? InternetAddress::random($first_address_version));
		}
		$this->putLong($this->client_send_time);
		$this->putLong($this->server_send_time);
	}

	public function deserializeBody(): void
	{
		$this->server_address = $this->readAddress();
		$this->is_minecraft_protocol = strlen($this->getBuffer()) - 1 - ($address_size = 1 + ($this->server_address->version === 4 ? 4 + 2 : 2 + 2 + 4 + 8 * 2 + 4)) - 2 - ($address_size * RakInfo::MAX_NUMBER_OF_MACHINE_ADDRESSES * 2) === 8 * 2;
		for ($i = 0; $i < RakInfo::MAX_NUMBER_OF_MACHINE_ADDRESSES * ($this->is_minecraft_protocol ? 1 : 1); ++$i) {
			$this->client_machine_addresses[] = $this->readAddress();
		}
		$this->client_send_time = $this->getLong();
		$this->server_send_time = $this->getLong();
	}
}
