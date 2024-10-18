<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use RuntimeException;
use Socket;

class UdpSocket
{

	public Socket|false $socket = false;

	public function __construct(
		public InternetAddress $address
	) {
		$this->socket = socket_create($address->version === 4 ? AF_INET : AF_INET6, SOCK_DGRAM, SOL_UDP);
	}

	public function receiveInfo(): array
	{
		$buffer = "";
		$ip = "";
		$port = 0;

		if (!@socket_recvfrom($this->socket, $buffer, 0xffff, 0, $ip, $port) || $port === 0 || $buffer === "") {
			return [];
		}

		return [new RakBinaryStream($buffer, 0), new InternetAddress($ip, $port, str_contains("::", $ip) ? 6 : 4)];
	}

	public function streamToSocket(string $buffer, InternetAddress $address): bool
	{
		$buffer_len = strlen($buffer);

		if ($buffer_len < 1) {
			return false;
		}

		return socket_sendto($this->socket, $buffer, strlen($buffer), 0, $address->ip, $address->port) > 0;
	}

	public function setNonBlocking(): void
	{
		socket_set_nonblock($this->socket);
	}

	public function bind(?InternetAddress $address = null): void
	{
		$addr = $address ?? $this->address;
		if (!@socket_bind($this->socket, $addr->ip, $addr->port)) {
			throw new RuntimeException("Failed to bind to $this->address: " . trim(socket_strerror(socket_last_error($this->socket))));
		}
	}

	public function connect(InternetAddress $target): void
	{
		if (!@socket_connect($this->socket, $target->ip, $target->port)) {
			throw new RuntimeException("Unalbe to connect to $target");
		}
	}

	public function close(): void
	{
		socket_close($this->socket);
	}
}
