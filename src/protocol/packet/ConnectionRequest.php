<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet;

use Laith98Dev\palethia\network\raknet\protocol\RakPacketId;
use Laith98Dev\palethia\network\raknet\RakInfo;
use RuntimeException;

class ConnectionRequest extends RakPacket
{
	public const PACKET_ID = RakPacketId::CONNECTION_REQUEST;

	public int $client_guid;
	public int $client_send_time;
	// format:
	// [
	//		"clientProof" => a client proof with 32 bytes as size
	//		"identityProof" => an identity proof with 294 bytes as size
	// ]
	public array $security = [];

	public function serializeBody(): void
	{
		$this->putLong($this->client_guid);
		$this->putLong($this->client_send_time);
		$this->putBool($do_security = !empty($this->security));
		if ($do_security) {
			$client_proof = $this->security["clientProof"];
			if (strlen($client_proof) !== RakInfo::CLIENT_PROOF_SIZE) {
				throw new RuntimeException("Invalid client proof size.");
			}
			$this->put($client_proof);
			$identity_proof = $this->security["identityProof"] ?? null;
			$this->putBool($do_identity = $identity_proof !== null);
			if ($do_identity) {
				if (strlen($identity_proof) !== RakInfo::IDENTITY_PROOF_SIZE) {
					throw new RuntimeException("Invalid identity proof size.");
				}
				$this->put($identity_proof);
			}
		}
	}

	public function deserializeBody(): void
	{
		$this->client_guid = $this->getLong();
		$this->client_send_time = $this->getLong();
		if ($this->getBool()) { // do security
			$this->security["clientProof"] = $this->get(RakInfo::CLIENT_PROOF_SIZE);
			$this->security["identitiyProof"] = $this->getBool() ? $this->get(RakInfo::IDENTITY_PROOF_SIZE) : null;
		}
	}
}
