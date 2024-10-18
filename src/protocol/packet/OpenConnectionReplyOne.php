<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakInfo;
use RuntimeException;

class OpenConnectionReplyOne extends RakPacket
{
	public const PACKET_ID = RakPacketId::OPEN_CONNECTION_REPLY_ONE;

	public int $server_guid;
	public bool $is_not_libcat_security = false;
	// format:
	// [
	//		"cookie" => an int representing the cookie
	//		"serverPublicKey" => an encryption key with 294 bytes (only if it isn't netty network)
	// ]
	public array $server_security = [];

	public int $mtu_size;

	public function serializeBody(): void
	{
		$this->writeMagic();
		$this->putLong($this->server_guid);
		$this->putBool($server_has_security = !empty($this->server_security));
		if ($server_has_security) {
			if ($this->is_not_libcat_security) {
				$this->putInt($this->server_security["cookie"]);
			} else {
				$cookie = $this->server_security["cookie"] ?? null;
				$this->putBool($has_cookie = $cookie !== null);
				if ($has_cookie) {
					$this->putInt($cookie);
				}
				$server_public_key = $this->server_security["serverPublicKey"];
				if (strlen($server_public_key) !== RakInfo::PUBLIC_ENCRYPTION_KEY_SIZE) {
					throw new RuntimeException("The given server public key to is not equal to " . RakInfo::PUBLIC_ENCRYPTION_KEY_SIZE);
				}
				$this->put($server_public_key);
			}
		}
		$this->putShort($this->mtu_size);
	}

	public function deserializeBody(): void
	{
		$this->readMagic();
		$this->server_guid = $this->getLong();

		if ($this->getBool()) { // server has security
			if ($this->is_not_libcat_security = strlen($this->buffer) - (1 + strlen(RakInfo::MAGIC) + 8 + 1 + 2) === 4) {
				$this->server_security["cookie"] = $this->getInt();
			} else {
				$this->server_security["cookie"] = $this->getBool() ? $this->getInt() : null;
				$this->server_security["serverPublicKey"] = $this->get(RakInfo::PUBLIC_ENCRYPTION_KEY_SIZE);
			}
		}

		$this->mtu_size = $this->getShort();
	}
}
