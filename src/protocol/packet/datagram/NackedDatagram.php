<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram;

use Laith98Dev\palethia\network\raknet\protocol\ds\RangeList;

class NackedDatagram extends Datagram
{

	public RangeList $range_list;

	public function serialize(): void
	{
		$this->nullifyBit();

		$this->range_list->serialize($this);
	}

	public function deserialize(): void
	{
		$this->nullifyBit();

		if (!isset($this->range_list)) {
			$this->range_list = new RangeList;
		}
		$this->range_list->deserialize($this);
	}
}
