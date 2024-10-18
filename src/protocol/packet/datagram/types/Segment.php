<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types;

use Laith98Dev\palethia\network\raknet\RakBinaryStream;

class Segment
{
	public int $size;
	public int $id;
	public int $index;

	public function serialize(RakBinaryStream $stream): void
	{
		$stream->putInt($this->size);
		$stream->putShort($this->id);
		$stream->putInt($this->index);
	}

	public function deserialize(RakBinaryStream $stream): void
	{
		$this->size = $stream->getInt();
		$this->id = $stream->getShort();
		$this->index = $stream->getInt();
	}
}
