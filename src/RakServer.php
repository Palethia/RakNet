<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use Laith98Dev\palethia\network\raknet\handler\RakNetHandler;

class RakServer extends RakPeer
{
	public function __construct(
		InternetAddress $address,
		public ?RakNetHandler $net_handler = null
	) {
		parent::__construct($address, $net_handler, $address->version === 4);

		$this->socket->bind();

		$this->socket->setNonBlocking();
	}
}
