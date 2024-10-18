<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\packet\datagram\types;

enum Reliability: int
{

	case UNRELIABLE = 0;
	case UNRELIABLE_SEQUENCED = 1;
	case RELIABLE = 2;
	case RELIABLE_ARRANGED = 3;
	case RELIABLE_SEQUENCED = 4;
	case UNRELIABLE_WITH_ACK_RECEIPT = 5;
	case RELIABLE_WITH_ACK_RECEIPT = 6;
	case RELIABLE_ARRANGED_WITH_ACK_RECEIPT = 7;

	public function isReliable(): bool
	{
		return $this === self::RELIABLE ||
			$this === self::RELIABLE_ARRANGED ||
			$this === self::RELIABLE_SEQUENCED ||
			$this === self::RELIABLE_WITH_ACK_RECEIPT ||
			$this === self::RELIABLE_ARRANGED_WITH_ACK_RECEIPT;
	}

	public function isSequenced(): bool
	{
		return $this === self::UNRELIABLE_SEQUENCED || $this === self::RELIABLE_SEQUENCED;
	}

	public function isSequencedOrArranged(bool $with_ack_receipt = true): bool
	{
		$is_sequenced_or_arranged = $this->isSequenced() || $this === self::RELIABLE_ARRANGED;

		return $with_ack_receipt ? $is_sequenced_or_arranged || $this === self::RELIABLE_ARRANGED_WITH_ACK_RECEIPT : $is_sequenced_or_arranged;
	}

	public function isReliableOrInSequence(bool $is_reliable_check = true): bool
	{
		$is_in_sequence = $this === self::RELIABLE_SEQUENCED || $this === self::RELIABLE_ARRANGED;
		return $is_reliable_check ? $this === self::RELIABLE || $is_in_sequence : $is_in_sequence;
	}

	public function isReliableArranged(): bool
	{
		return $this === self::RELIABLE_ARRANGED || self::RELIABLE_ARRANGED_WITH_ACK_RECEIPT;
	}

	public function convertToReliableIfUnreliable(): self
	{
		return match(true) {
			$this === self::UNRELIABLE => self::RELIABLE,
			$this === self::UNRELIABLE_SEQUENCED => self::RELIABLE_SEQUENCED,
			$this === self::UNRELIABLE_WITH_ACK_RECEIPT => self::RELIABLE_WITH_ACK_RECEIPT,
			default => $this
		};
	}
}
