<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types;

use Laith98Dev\palethia\network\raknet\RakBinaryStream;

class Arrangement
{
	public int $index;
	public int $channel;

	public function serialize(RakBinaryStream $stream): void
	{
		$stream->putLTriad($this->index);
		$stream->putByte($this->channel);
	}

	public function deserialize(RakBinaryStream $stream): void
	{
		$this->index = $stream->getLTriad();
		$this->channel = $stream->getByte();
	}
}
