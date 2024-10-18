<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types;

use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Arrangement;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Reliability;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types\Segment;
use Laith98Dev\palethia\network\raknet\RakBinaryStream;

class Capsule extends RakBinaryStream
{

	public Reliability $reliability;
	public ?Segment $segment = null;

	public int $reliable_index;
	public int $sequenced_index;

	public ?Arrangement $arrangement = null;

	public RakBinaryStream $stream;

	public function serialize(): void
	{
		$this->writeBits($this->reliability->value, 3);
		$this->writeBit($is_segmented = $this->segment !== null, true);

		$buffer = $this->stream->getBuffer();

		$this->putShort(strlen($buffer) << RakBinaryStream::BIT_SHIFT_BIT_COUNT);

		if ($this->reliability->isReliable()) {
			$this->putLTriad($this->reliable_index);
		}
		if ($this->reliability->isSequenced()) {
			$this->putLTriad($this->sequenced_index);
		}

		if ($this->reliability->isSequencedOrArranged()) {
			$this->arrangement->serialize($this);
		}

		if ($is_segmented) {
			$this->segment->serialize($this);
		}

		$this->put($buffer);
	}

	public function deserialize(): void
	{
		$this->reliability = Reliability::from($this->readBits(3));
		$is_segmented = $this->readBit();
		$this->nullifyBit();

		$stream_size = $this->getShort() >> RakBinaryStream::BIT_SHIFT_BIT_COUNT;

		if ($this->reliability->isReliable()) {
			$this->reliable_index = $this->getLTriad();
		}
		if ($this->reliability->isSequenced()) {
			$this->sequenced_index = $this->getLTriad();
		}

		if ($this->reliability->isSequencedOrArranged()) {
			if ($this->arrangement === null) {
				$this->arrangement = new Arrangement;
			}
			$this->arrangement->deserialize($this);
		}

		if ($is_segmented) {
			if ($this->segment === null) {
				$this->segment = new Segment;
			}

			$this->segment->deserialize($this);
		}

		$this->stream = new RakBinaryStream($this->get($stream_size));
	}

	public function getSize(): int
	{
		$result = 1;
		$result += 2;

		if ($this->reliability->isReliable()) {
			$result += 3;
		}
		if ($this->reliability->isSequenced()) {
			$result += 3;
		}
		if ($this->reliability->isSequencedOrArranged()) {
			$result += 3;
			$result += 1;
		}
		if ($this->segment !== null) {
			$result += 4;
			$result += 2;
			$result += 4;
		}

		return $result;
	}
}
