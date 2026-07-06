<?php
namespace GT\Website\Blueprint;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<Blueprint> */
class BlueprintList implements Countable, IteratorAggregate {
	/** @var array<Blueprint> */
	private array $blueprintList;

	public function __construct(Blueprint...$blueprintList) {
		$this->blueprintList = $blueprintList;
	}

	public function count():int {
		return count($this->blueprintList);
	}

	public function getIterator():Traversable {
		return new ArrayIterator($this->blueprintList);
	}
}
