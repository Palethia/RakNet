<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

class CongestionManager
{

	private const HALF_SPAN = 0x7fffff;

	public int $expected_next_range_number = 0;

	public function __construct(
		private int $mtu_size
	) {
		// NOOP
	}

	public function calcSkippedRangeNumberCount(int $range_number): int
	{
		$result = 0;
		$update_prediction = false;

		if ($range_number === $this->expected_next_range_number) {
			$update_prediction = true;
		} else if ($this->isGreaterThan($range_number, $this->expected_next_range_number)) {
			$result = $range_number - $this->expected_next_range_number;

			if ($result > 0x3e8) {
				if ($result > 0xc350) {
					return -1;
				}
				$result = 0x3e8;
			}

			$update_prediction = true;
		}

		if ($update_prediction) $this->expected_next_range_number = $range_number + 1;

		return $result;
	}

	private function isGreaterThan(int $first, int $last): bool
	{
		return $last !== $first && ((($last - $first) & 0xffffff) > self::HALF_SPAN);
	}

	public function calcMaxDgramSendCount(): array
	{
		$adjustment = 440;
		$shift = 7;

		if ($this->mtu_size <= $max_mtu_size = RakInfo::MAXIMUM_MTU_SIZE) {
			$def_adjustment = 145;
			$def_shift = 4;

			$adjustment_scale = 1.0;
			$shift_scale = 1.0;

			$factor = ($this->mtu_size - $max_mtu_size) / (3000 - $max_mtu_size);

			$adjustment = intval($def_adjustment + $factor * ($adjustment_scale * 200 - $def_adjustment));
			$shift_to_set = intval($def_shift + $factor * ($shift_scale * 5 - $def_shift));

			if ($shift_to_set === 3 && $this->mtu_size >= 1000) $shift_to_set = 4;
			if ($shift_to_set < $shift) $shift = $shift_to_set;
		}

		return [($this->mtu_size - $adjustment) >> $shift, $adjustment > 100 ? $adjustment >> 3 : $adjustment];
	}
}
