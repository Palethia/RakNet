<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

class RakInfo
{
	public const MAGIC = "\x00\xFF\xFF\x00\xFE\xFE\xFE\xFE\xFD\xFD\xFD\xFD\x12\x34\x56\x78";

	public const PROTOCOL_VERSION = 11;
	public const MAXIMUM_MTU_SIZE = 1492;
	public const MINECRAFT_MTU_SIZE = 1400;
	public const UDP_HEADER_SIZE = 28;
	public const PUBLIC_ENCRYPTION_KEY_SIZE = 294;
	public const CHALLENGE_SIZE = 64;
	public const ENCRYPTION_KEY_SIZE = 128;
	public const CLIENT_PROOF_SIZE = 32;
	public const IDENTITY_PROOF_SIZE = 294;
	public const MAX_NUMBER_OF_MACHINE_ADDRESSES = 10;
	public const NUMBER_OF_ARRANGED_STREAMS = 32;

	public const MTU_SIZES = [self::MAXIMUM_MTU_SIZE, 1200, 576];
}
