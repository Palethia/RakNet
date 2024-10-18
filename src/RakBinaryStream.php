<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use pocketmine\utils\BinaryStream;
use RuntimeException;

class RakBinaryStream extends BinaryStream
{

	public const BIT_SHIFT_BIT_COUNT = 3;

	// bit system copied from https://github.com/vp817/CppBinaryStream
	protected int $current_octet = 0;
	protected int $bit_count = 0;

	public function writeMagic(): void
	{
		$this->put(RakInfo::MAGIC);
	}

	public function readMagic(): void
	{
		if ($this->get(strlen(RakInfo::MAGIC)) !== RakInfo::MAGIC) {
			throw new RuntimeException("Unable to read correct packet magic.");
		}
	}

	public function writeString(string $value): void
	{
		$this->putShort(strlen($value));
		$this->put($value);
	}

	public function readString(): string
	{
		return $this->get($this->getShort());
	}

	public function writeAddress(InternetAddress $value): void
	{
		$version = $value->version;

		if ($version === 4) {
			$this->putByte($version);

			$parts = explode(".", $value->ip);

			for ($i = 0; $i < count($parts); ++$i) {
				$this->putByte(~intval($parts[$i]) & 0xff);
			}

			$this->putShort($value->port);
		} else if ($version === 6) {
			$this->putByte($version);

			$parts = explode(":", $value->ip);

			$this->putLShort(AF_INET6);
			$this->putShort($value->port);
			$this->putInt(0); // flow info

			for ($i = 0; $i < count($parts); ++$i) {
				$this->putShort(hexdec($parts[$i]));
			}

			$this->putInt(0); // scope id
		} else {
			throw new RuntimeException("Invalid address version $version.");
		}
	}

	public function readAddress(): InternetAddress
	{
		$version = $this->getByte();

		$ip = "";
		$port = InternetAddress::UNASSINGED_ADDRESS_PORT;

		if ($version === 4) {
			for ($i = 0; $i < 4; ++$i) {
				$ip .= strval(~$this->getByte() & 0xff) . ".";
			}

			$port = $this->getShort();
		} else if ($version === 6) {
			$this->offset += 2; // address family
			$port = $this->getShort();
			$this->offset += 4; // flow info

			for ($i = 0; $i < 16; $i += 2) {
				$ip .= str_pad(dechex($this->getShort()), 4, "0", STR_PAD_LEFT) . ":";
			}

			$this->offset += 4; // scope id
		} else {
			throw new RuntimeException("Invalid address version $version.");
		}

		return new InternetAddress(substr($ip, 0, -1), $port, $version);
	}

	public function nullifyBit(): void
	{
		if ($this->current_octet != 0 && $this->bit_count != 0) {
			$this->current_octet = 0;
			$this->bit_count = 0;
		}
	}

	public function writeBit(bool $value, bool $skip = false): void
	{
		if ($this->current_octet !== 0 && $this->bit_count === 0) {
			$this->current_octet = 0;
		}

		$this->current_octet |= ($value === true ? 1 : 0) << (7 - $this->bit_count++);

		if ($this->bit_count === 8 || $skip) {
			$this->putByte($this->current_octet);
			$this->current_octet = 0;
			$this->bit_count = 0;
		}
	}

	public function setupBits(int $current_octet, int $bit_count): void
	{
		$this->current_octet = $current_octet;
		$this->bit_count = $bit_count;
	}

	public function readBit(bool $skip = false): bool
	{
		if ($this->current_octet != 0 && $this->bit_count == 0) {
			$this->current_octet = 0;
		}

		if ($this->bit_count == 0 || $skip) {
			$this->current_octet = $this->getByte();
			$this->bit_count = 8;
		}

		return ($this->current_octet & (1 << (--$this->bit_count))) != 0;
	}

	public function writeBits(int $value, int $size, bool $big_endian = true): void
	{
		for ($i = 0; $i < $size; ++$i) {
			$this->writeBit((($value >> ($big_endian ? ($size - $i - 1) : $i)) & 1) == 1);
		}
	}

	public function readBits(int $size, bool $big_endian = true): int
	{
		$result = 0;

		for ($i = 0; $i < $size; ++$i) {
			$result |= ($this->readBit() === true ? 0x01 : 0x00) << ($big_endian ? ($size - $i - 1) : $i);
		}

		return $result;
	}
}
