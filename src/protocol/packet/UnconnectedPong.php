<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;

class UnconnectedPong extends RakPacket
{
	public const PACKET_ID = RakPacketId::UNCONNECTED_PONG;

	public int $server_send_time;
	public int $server_guid;
	public string $response_data;

	public function serializeBody(): void
	{
		$this->putLong($this->server_send_time);
		$this->putLong($this->server_guid);
		$this->writeMagic();
		$this->writeString($this->response_data);
	}

	public function deserializeBody(): void
	{
		$this->server_send_time = $this->getLong();
		$this->server_guid = $this->getLong();
		$this->readMagic();
		$this->response_data = $this->readString();
	}
}
