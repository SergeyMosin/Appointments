<?php

namespace OCA\Appointments\Backend;

use OCA\Appointments\IntervalTree\AVLIntervalNode;
use OCA\Appointments\IntervalTree\AVLIntervalTree;

final class ExternalSlotCapacity
{
	private array $sources = [];
	private array $claims = [];

	public function addSource(string $uri, int $start, int $end): void
	{
		$this->sources[$uri][$this->occurrenceKey($start, $end)] = true;
	}

	public function claim(string $uri, int $start, int $end): bool
	{
		$key = $this->occurrenceKey($start, $end);
		if (!isset($this->sources[$uri][$key])) {
			return false;
		}

		$this->claims[$uri][$key] = true;
		return true;
	}

	public function registerDestination(string|null $sourceUri, int $start, int $end): bool
	{
		return $sourceUri !== null && $this->claim($sourceUri, $start, $end);
	}

	public function isClaimed(string $uri, int $start, int $end): bool
	{
		return isset($this->claims[$uri][$this->occurrenceKey($start, $end)]);
	}

	public function isAvailable(
		string $uri,
		int $start,
		int $end,
		AVLIntervalNode|null $busyTree
	): bool {
		$key = $this->occurrenceKey($start, $end);

		return isset($this->sources[$uri][$key])
			&& !isset($this->claims[$uri][$key])
			&& AVLIntervalTree::lookUp($busyTree, $start, $end) === null;
	}

	public function hasBookingConflict(
		string $uri,
		int $start,
		int $end,
		AVLIntervalNode|null $busyTree
	): bool {
		return !$this->isAvailable($uri, $start, $end, $busyTree);
	}

	private function occurrenceKey(int $start, int $end): string
	{
		return $start . ':' . $end;
	}
}
