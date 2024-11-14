<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\connection;

use Laith98Dev\palethia\network\raknet\InternetAddress;

class ConnectionManager
{

	/** @var Connection[] */
	public array $list = [];
	/** @var Connection[] */
	public array $glist = [];

	public function add(Connection $value): bool
	{
		if (isset($this->list[$str_address = strval($value->address)]) || isset($this->glist[$guid = $value->guid])) {
			return false;
		}

		$this->list[$str_address] = $value;
		$this->glist[$guid] = $value;

		return true;
	}

	public function get(string|int $value): ?Connection
	{
		return is_string($value) ? $this->list[$value] ?? null : $this->glist[$value] ?? null;
	}

	public function removeOneside(string|int $value): bool
	{
		$is_guid = is_int($value);
		if ($is_guid ? !isset($this->glist[$value]) : !isset($this->list[$value])) {
			return false;
		}

		if ($is_guid) {
			unset($this->glist[$value]);
		} else {
			unset($this->list[$value]);
		}

		return true;
	}

	public function remove(Connection $value): void
	{
		$this->removeOneside(strval($value->address));
		$this->removeOneside($value->guid);
	}

	public function getIndex(InternetAddress|int $value): int
	{
		$is_guid = is_int($value);
		if (($is_guid ? !isset($this->glist[$value]) : !isset($this->list[strval($value)])) || (empty($this->list) & empty($this->glist))) {
			var_dump("ConnectionManager - $value is not included in list to get it's index.");
			return 0;
		}

		$index = 0;

		foreach ($this->list as $connection) {
			if ($is_guid ? $connection->guid !== $value : !$connection->address->equals($value)) {
				++$index;
				continue;
			}
			break;
		}

		return $index;
	}
}
