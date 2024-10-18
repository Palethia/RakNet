<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakInfo;

class OpenConnectionRequestOne extends RakPacket
{
	public const PACKET_ID = RakPacketId::OPEN_CONNECTION_REQUEST_ONE;

	public int $protocol_version;
	public int $mtu_size;

	public function serializeBody(): void
	{
		$this->writeMagic();
		$this->putByte($this->protocol_version);
		$this->put(str_repeat("\x00", $this->mtu_size - (strlen($this->buffer) + RakInfo::UDP_HEADER_SIZE)));
	}

	public function deserializeBody(): void
	{
		$this->readMagic();
		$this->protocol_version = $this->getByte();
		$this->mtu_size = strlen($this->getRemaining()) + (strlen($this->buffer) + RakInfo::UDP_HEADER_SIZE);
	}
}
