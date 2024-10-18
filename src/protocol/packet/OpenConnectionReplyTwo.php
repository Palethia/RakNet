<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\InternetAddress;
use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakInfo;
use RuntimeException;

class OpenConnectionReplyTwo extends RakPacket
{
	public const PACKET_ID = RakPacketId::OPEN_CONNECTION_REPLY_TWO;

	public int $server_guid;
	public InternetAddress $client_address;
	public int $mtu_size;
	// format:
	// [
	//		"key" => an encryption key with 128 bytes
	// ]
	public array $encryption = [];

	public function serializeBody(): void
	{
		$this->writeMagic();
		$this->putLong($this->server_guid);
		$this->writeAddress($this->client_address);
		$this->putShort($this->mtu_size);
		$requires_encryption = !empty($this->encryption);
		$this->writeBit($requires_encryption, true);
		if ($requires_encryption) {
			$encryption_key = $this->encryption["key"];
			if (strlen($encryption_key) !== RakInfo::ENCRYPTION_KEY_SIZE) {
				throw new RuntimeException("The given encryption key size is not equal to " . RakInfo::ENCRYPTION_KEY_SIZE);
			}
			$this->put($encryption_key);
		}
	}

	public function deserializeBody(): void
	{
		$this->readMagic();
		$this->server_guid = $this->getLong();
		$this->client_address = $this->readAddress();
		$this->mtu_size = $this->getShort();

		if ($this->readBit()) { // requires encryption
			$this->encryption["key"] = $this->get(RakInfo::ENCRYPTION_KEY_SIZE);
		}

		$this->nullifyBit();
	}
}
