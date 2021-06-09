<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block\inventory;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\world\Position;

class BeaconInventory extends SimpleInventory implements BlockInventory{

	public const SLOT_FUEL = 0;

	private Position $holder;

	public function __construct(Position $holder){
		parent::__construct(1);
		$this->holder = $holder;
	}

	public function getFuelItem() : Item{
		return $this->getItem(self::SLOT_FUEL);
	}

	public function setFuelItem(Item $item) : void{
		$this->setItem(self::SLOT_FUEL, $item);
	}

	public function getHolder() : Position{
		return $this->holder;
	}
}