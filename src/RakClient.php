<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use Laith98Dev\palethia\network\raknet\handler\RakNetHandler;

class RakClient extends RakPeer
{

    public function __construct(
        public InternetAddress $client_address,
        InternetAddress $server_address,
        ?RakNetHandler $net_handler = null
    ) {
        parent::__construct($server_address, $net_handler, $client_address->version === 4);

        $this->socket->bind($client_address);

        $this->socket->connect($server_address);

        $this->upstream->unconnectedPing($server_address);
    }
}
