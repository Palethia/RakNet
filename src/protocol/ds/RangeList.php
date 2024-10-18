<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\protocol\ds;

use Laith98Dev\palethia\network\raknet\RakBinaryStream;

class RangeList
{

	/**
	 * @param RangeNode[] $nodes
	 */
	public function __construct(
		public array $nodes = []
	) {
		// NOOP
	}

	public function insert(int $index): void
	{
		$nodes_count = count($this->nodes);

		if ($nodes_count < 1) {
			$this->nodes[] = new RangeNode($index, $index);
			return;
		}

		$insertion_index = $nodes_count;
		$object_exists = false;

		for ($i = 0; $i < $nodes_count; $i++) {
			if ($index < $this->nodes[$i]->min - 1 || $index >= $this->nodes[$i]->min && $index <= $this->nodes[$i]->max) {
				continue;
			} else if ($index === $this->nodes[$i]->min - 1 || $index === $this->nodes[$i]->max + 1) {
				$object_exists = true;
				$insertion_index = $i;
				break;
			}
		}

		if (!$object_exists) {
			if ($insertion_index === $nodes_count) {
				if ($index === $this->nodes[$insertion_index - 1]->max + 1) {
					$this->nodes[$insertion_index - 1]->max++;
				} else {
					$this->nodes[] = new RangeNode($index, $index);
				}
			} else {
				if ($index < $this->nodes[$insertion_index]->min - 1) {
					array_splice($this->nodes, $insertion_index, 0, new RangeNode($index, $index));
				} else if ($index === $this->nodes[$insertion_index]->min - 1) {
					$this->nodes[$insertion_index]->min--;
					if ($insertion_index > 0 && $this->nodes[$insertion_index - 1]->max + 1 === $this->nodes[$insertion_index]->min) {
						$this->nodes[$insertion_index - 1]->max = $this->nodes[$insertion_index]->max;
						unset($this->nodes[$insertion_index]);
					}
				} else if ($index === $this->nodes[$insertion_index]->max + 1) {
					$this->nodes[$insertion_index]->max++;
					if ($insertion_index < $nodes_count - 1 && $this->nodes[$insertion_index + 1]->min === $this->nodes[$insertion_index]->max + 1) {
						$this->nodes[$insertion_index + 1]->min = $this->nodes[$insertion_index]->min;
						unset($this->nodes[$insertion_index]);
					}
				}
			}
		}
	}

	public function serialize(RakBinaryStream $stream): void
	{
		$nodes_count = count($this->nodes);
		$stream->putShort($nodes_count);

		for ($i = 0; $i < $nodes_count; ++$i) {
			$node = $this->nodes[$i];

			$is_single = $node->min === $node->max;

			$stream->putBool($is_single);

			$stream->putLTriad($node->min);
			if (!$is_single) $stream->putLTriad($node->max);
		}
	}

	public function deserialize(RakBinaryStream $stream): void
	{
		$nodes_count = $stream->getShort();

		for ($i = 0; $i < $nodes_count; ++$i) {
			$is_single = $stream->getBool();

			$min = $stream->getLTriad();
			$max = !$is_single ? $stream->getLTriad() : $min;

			if (!$is_single) {
				if ($max < $min) {
					var_dump("max lower than min[dsrangelist], skipped range.");
					return;
				}
			}

			$this->nodes[] = new RangeNode($min, $max);
		}
	}
}
