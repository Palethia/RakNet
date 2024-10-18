<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakInfo;
use RuntimeException;

class OpenConnectionRequestTwo extends RakPacket
{
	public const PACKET_ID = RakPacketId::OPEN_CONNECTION_REQUEST_TWO;

	public bool $reply_one_has_security = false;
	// format:
	// [
	//		"cookie" => an int representing the cookie
	//		"challenge" => a challenge with 64 bytes
	// ]
	public array $client_security = [];
	public InternetAddress $server_address;
	public int $mtu_size;
	public int $client_guid;

	public function serializeBody(): void
	{
		$this->writeMagic();
		if ($this->reply_one_has_security) {
			$this->putInt($this->client_security["cookie"]);
			$challenge = $this->client_security["challenge"] ?? null;
			$this->putBool($has_challenge = $challenge !== null);
			if ($has_challenge) {
				$challenge = $this->client_security["challenge"];
				if (strlen($challenge) !== RakInfo::CHALLENGE_SIZE) {
					throw new RuntimeException("The given challenge size is not equal to " . RakInfo::CHALLENGE_SIZE);
				}
				$this->put($challenge);
			}
		}
		$this->writeAddress($this->server_address);
		$this->putShort($this->mtu_size);
		$this->putLong($this->client_guid);
	}

	public function deserializeBody(): void
	{
		$this->readMagic();
		if ($this->reply_one_has_security && empty($this->client_security)) {
			$this->client_security["cookie"] = $this->getInt();
			$this->client_security["challenge"] = $this->getBool() ? $this->get(RakInfo::CHALLENGE_SIZE) : null;
		}
		$this->server_address = $this->readAddress();
		$this->mtu_size = $this->getShort();
		$this->client_guid = $this->getLong();
	}
}
