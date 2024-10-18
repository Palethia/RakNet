<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;

class AlreadyConnected extends RakPacket
{
	public const PACKET_ID = RakPacketId::ALREADY_CONNECTED;

	public int $client_guid;

	public function serializeBody(): void
	{
		$this->writeMagic();
		$this->putLong($this->client_guid);
	}

	public function deserializeBody(): void
	{
		$this->readMagic();
		$this->client_guid = $this->getLong();
	}
}
