<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;

class ConnectedPing extends RakPacket
{
	public const PACKET_ID = RakPacketId::CONNECTED_PING;

	public int $client_send_time;

	public function serializeBody(): void
	{
		$this->putLong($this->client_send_time);
	}

	public function deserializeBody(): void
	{
		$this->client_send_time = $this->getLong();
	}
}
