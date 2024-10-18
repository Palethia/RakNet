<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram;

use Laith98Dev\palethia\console\Logger;
use Laith98Dev\palethia\network\raknet\RakBinaryStream;

class DatagramIdentifier extends RakBinaryStream
{

	public Datagram $identifing_datagram;

	public function serialize(): void
	{
		$this->writeBit(($this->identifing_datagram instanceof ValidDatagram || $this->identifing_datagram instanceof AckedDatagram || $this->identifing_datagram instanceof NackedDatagram));
		$this->writeBit($this->identifing_datagram instanceof AckedDatagram);
		$this->writeBit($this->identifing_datagram instanceof NackedDatagram);

		$this->identifing_datagram->setupBits($this->current_octet, $this->bit_count);
		$this->identifing_datagram->serialize();
	}

	public function deserialize(): void
	{
		$is_valid = $this->readBit();
		$is_ack = $this->readBit();
		$is_nack = $this->readBit();

		$create_datagram_func = function (string $class): Datagram {
			return new $class($this->buffer, $this->offset);
		};

		if (!$is_valid) {
			Logger::warning("Received not valid datagram. (selected invalid datagram)");
			$this->identifing_datagram = $create_datagram_func(InvalidDatagram::class);
			return;
		}

		if ($is_ack) {
			$this->identifing_datagram = $create_datagram_func(AckedDatagram::class);
		} else if ($is_nack) {
			$this->identifing_datagram = $create_datagram_func(NackedDatagram::class);
		} else {
			$this->identifing_datagram = $create_datagram_func(ValidDatagram::class);
		}

		$this->identifing_datagram->setupBits($this->current_octet, $this->bit_count);
		$this->identifing_datagram->deserialize();
	}
}
