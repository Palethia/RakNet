<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use Laith98Dev\palethia\network\raknet\protocol\ds\RangeList;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\AckedDatagram;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\DatagramIdentifier;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\NackedDatagram;
use Laith98Dev\palethia\network\raknet\protocol\packet\datagram\ValidDatagram;

final class DatagramBuilder
{
    public static function createAcknowledge(RangeList $range_list, bool $negative = false): DatagramIdentifier
    {
        $datagram = $negative ? new NackedDatagram() : new AckedDatagram();
        $datagram->range_list = $range_list;
        $identifier = new DatagramIdentifier();
        $identifier->identifing_datagram = $datagram;
        return $identifier;
    }

    public static function createDatagram(int $range_number, array $capsules): DatagramIdentifier
    {
        $datagram = new ValidDatagram();
        $datagram->range_number = $range_number;
        $datagram->capsules = $capsules;
        $identifier = new DatagramIdentifier();
        $identifier->identifing_datagram = $datagram;
        return $identifier;
    }

    public static function createRawDatagram(): DatagramIdentifier
    {
        $datagram = new ValidDatagram();
        $datagram->range_number = 0;
        $datagram->capsules = [];
        $identifier = new DatagramIdentifier();
        $identifier->identifing_datagram = $datagram;
        return $identifier;
    }
}
