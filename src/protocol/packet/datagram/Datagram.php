<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram;

use Laith98Dev\palethia\network\raknet\RakBinaryStream;

abstract class Datagram extends RakBinaryStream
{
	public abstract function serialize(): void;

	public abstract function deserialize(): void;

	public static function getHeaderSize(): int
	{
		return 2 + 3 + 4 * 1;
	}
}
