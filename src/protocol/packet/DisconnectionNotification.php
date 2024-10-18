<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;

class DisconnectionNotification extends RakPacket
{
	public const PACKET_ID = RakPacketId::DISCONNECTION_NOTIFICATION;

	public function serializeBody(): void
	{
		// NOOP
	}

	public function deserializeBody(): void
	{
		// NOOP
	}
}
