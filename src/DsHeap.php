<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use ArrayAccess;
use Countable;
use OutOfBoundsException;

class DsHeap implements Countable, ArrayAccess
{
	private array $heap = [];
	private bool $optimize_next_series_push = false;

	public function __construct(
		private bool $is_max_heap = true
	) {
		// NOOP
	}

	public function push(int $weight, mixed $data): void
	{
		$current_index = count($this->heap);
		$this->heap[] = ["weight" => $weight, "data" => $data];

		while ($current_index != 0) {
			$parent_index = $this->parent($current_index);
			$heap_weight = $this->heap[$parent_index]["weight"];
			if ($this->is_max_heap ? $heap_weight < $weight : $heap_weight > $weight) {
				$this->swap($current_index, $parent_index);
				$current_index = $parent_index;
			} else {
				break;
			}
		}
	}

	public function pushSeries(int $weight, mixed $data): void
	{
		if (!$this->optimize_next_series_push) {
			$current_index = count($this->heap);
			$parent_index = $this->parent($current_index);
			if ($current_index > 0) {
				for (; $parent_index < $current_index; $parent_index++) {
					$heap_weight = $this->heap[$parent_index]["weight"];
					if ($this->is_max_heap ? $weight > $heap_weight : $weight < $heap_weight) {
						$this->push($weight, $data);
						return;
					}
				}
			}
			$this->heap[] = ["weight" => $weight, "data" => $data];
			$this->optimize_next_series_push = true;
		} else {
			$this->heap[] = ["weight" => $weight, "data" => $data];
		}
	}

	public function pop(int $starting_index): mixed
	{
		$returnValue = $this->heap[$starting_index]["data"];
		$this->heap[$starting_index] = array_pop($this->heap);
		$current_index = $starting_index;
		$current_weight = $this->heap[$starting_index]["weight"];

		while (true) {
			$left_child = $current_index * 2 + 1;
			$right_child = $current_index * 2 + 2;

			if ($left_child >= count($this->heap)) {
				return $returnValue;
			}

			if ($right_child >= count($this->heap)) {
				if (
					($this->is_max_heap && $current_weight < $this->heap[$left_child]["weight"]) ||
					(!$this->is_max_heap && $current_weight > $this->heap[$left_child]["weight"])
				) {
					$this->swap($left_child, $current_index);
				}

				return $returnValue;
			} else {
				$left_heap_weight = $this->heap[$left_child]["weight"];
				$right_heap_weight = $this->heap[$right_child]["weight"];

				if (
					$this->is_max_heap ?
					$left_heap_weight <= $current_weight && $right_heap_weight <= $current_weight :
					$left_heap_weight >= $current_weight && $right_heap_weight >= $current_weight
				) {
					return $returnValue;
				}

				if (
					$this->is_max_heap ?
					$left_heap_weight > $right_heap_weight :
					$left_heap_weight < $right_heap_weight
				) {
					$this->swap($left_child, $current_index);
					$current_index = $left_child;
				} else {
					$this->swap($right_child, $current_index);
					$current_index = $right_child;
				}
			}
		}
	}

	public function peek(int $starting_index = 0): mixed
	{
		return $this->heap[$starting_index]["data"];
	}

	public function peekWeight(int $starting_index = 0): mixed
	{
		return $this->heap[$starting_index]["weight"];
	}

	public function clear(): void
	{
		$this->heap = [];
	}

	public function count(): int
	{
		return count($this->heap);
	}

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->heap[$offset]);
	}

	public function offsetGet(mixed $offset): mixed
	{
		if (isset($this->heap[$offset])) {
			return $this->heap[$offset]["data"];
		}
		return false;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!isset($this->heap[$offset])) {
			throw new OutOfBoundsException("Invalid heap offset $offset");
		}
		$this->heap[$offset]["data"] = $value;
	}

	public function offsetUnset(mixed $offset): void
	{
		if (isset($this->heap[$offset])) {
			unset($this->heap[$offset]);
			$this->heap = array_values((array)$this->heap);
		}
	}

	private function parent(int $value): int
	{
		if ($value === 0) {
			throw new OutOfBoundsException("No parent for index 0");
		}
		return (int)(($value - 1) / 2);
	}

	private function swap(int $x, int $y)
	{
		$temp = $this->heap[$x];
		$this->heap[$x] = $this->heap[$y];
		$this->heap[$y] = $temp;
	}
}
