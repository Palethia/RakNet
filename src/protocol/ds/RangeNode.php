<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\ds;

class RangeNode
{
	public function __construct(
		public int $min,
		public int $max,
	) {
		// NOOP
	}
}
