<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram;

use Laith98Dev\palethia\network\raknet\protocol\ds\RangeList;

class AckedDatagram extends Datagram
{

	public bool $requires_b_and_as = false;
	// public float $b; not used
	public float $as;

	public RangeList $range_list;

	public function serialize(): void
	{
		$this->writeBit($this->requires_b_and_as, true);

		if ($this->requires_b_and_as) {
			// $this->putFloat($this->b); not used
			$this->putFloat($this->as);
		}

		$this->range_list->serialize($this);
	}

	public function deserialize(): void
	{
		$this->requires_b_and_as = $this->readBit();
		$this->nullifyBit();

		if ($this->requires_b_and_as) {
			// $this->b = $this->getFloat(); not used
			$this->as = $this->getFloat();
		}

		if (!isset($this->range_list)) { // do something later
			$this->range_list = new RangeList;
		}
		$this->range_list->deserialize($this);
	}
}
