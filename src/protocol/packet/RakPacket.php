<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakBinaryStream;
use RuntimeException;

abstract class RakPacket extends RakBinaryStream
{
	public const PACKET_ID = RakPacketId::INVALID;

	public function getId(): RakPacketId
	{
		return static::PACKET_ID;
	}

	public function serialize(): void
	{
		$this->serializeHeader();
		$this->serializeBody();
	}

	public function deserialize(): void
	{
		$this->deserializeHeader();
		$this->deserializeBody();
	}

	public function serializeHeader(): void
	{
		$this->putByte($this->getId()->value);
	}

	public function deserializeHeader(): void
	{
		if ($id = $this->getByte() != $cur_id = $this->getId()->value) {
			throw new RuntimeException("Trying to serialize a packet with an invalid id, expected $cur_id, got $id");
		}
	}

	public abstract function serializeBody(): void;

	public abstract function deserializeBody(): void;
}
