<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\handler;

use Laith98Dev\palethia\network\raknet\connection\Connection;
use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\RakBinaryStream;

abstract class RakNetHandler
{
	/**
	 * Handle a connection.
	 * 
	 * @var Connection $connection
	 * 
	 * @return void
	 */
	public abstract function handleConnection(Connection $connection): void;

	/**
	 * Handle a disconnection.
	 * 
	 * @var InternetAddress $address
	 * 
	 * @return void
	 */
	public abstract function handleDisconnection(InternetAddress $address): void;

	/**
	 * Handle an incoming packet.
	 * 
	 * @var RakBinaryStream $stream
	 * @var InternetAddress $address
	 * 
	 * @return void
	 */
	public abstract function handlePacket(RakBinaryStream $stream, InternetAddress $address): void;
}
