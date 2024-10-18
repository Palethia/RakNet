<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;

class UnconnectedPing extends RakPacket
{
	public const PACKET_ID = RakPacketId::UNCONNECTED_PING;

	public bool $only_reply_on_open_connections = false;
	public int $client_send_time;
	public int $client_guid;

	public function getId(): RakPacketId
	{
		return $this->only_reply_on_open_connections ? RakPacketId::UNCONNECTED_PING_OPEN_CONNECTIONS : self::PACKET_ID;
	}

	public function serializeBody(): void
	{
		$this->putLong($this->client_send_time);
		$this->writeMagic();
		$this->putLong($this->client_guid);
	}

	public function deserializeBody(): void
	{
		$this->client_send_time = $this->getLong();
		$this->readMagic();
		$this->client_guid = $this->getLong();
	}
}
