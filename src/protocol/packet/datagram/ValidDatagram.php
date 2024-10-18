<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram;

use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Capsule;

class ValidDatagram extends Datagram
{

	public bool $is_packet_pair = false;
	public bool $is_continuous_send = false;
	public bool $requires_b_and_as = false;

	public int $range_number;

	public array $capsules = [];

	public function serialize(): void
	{
		$this->writeBit($this->is_packet_pair);
		$this->writeBit($this->is_continuous_send);
		$this->writeBit($this->requires_b_and_as, true);

		$this->putLTriad($this->range_number);

		for ($i = 0; $i < count($this->capsules); ++$i) {
			$capsule = array_shift($this->capsules);
			$capsule->serialize();

			$this->put($capsule->getBuffer());
		}
	}

	public function deserialize(): void
	{
		$this->is_packet_pair = $this->readBit();
		$this->is_continuous_send = $this->readBit();
		$this->requires_b_and_as = $this->readBit();
		$this->nullifyBit();

		$this->range_number = $this->getLTriad();

		while (!$this->feof()) {
			$capsule = new Capsule($this->buffer, $this->offset);
			$capsule->deserialize();

			$this->capsules[] = $capsule;

			$this->offset = $capsule->offset;
		}
	}
}
