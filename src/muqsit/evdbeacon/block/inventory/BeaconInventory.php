<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block\inventory;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\inventory\BlockInventoryTrait;
use pocketmine\inventory\SimpleInventory;
use pocketmine\item\Item;
use pocketmine\world\Position;

class BeaconInventory extends SimpleInventory implements BlockInventory{
    use BlockInventoryTrait;

	public const SLOT_FUEL = 0;

	public function __construct(Position $holder){
	    $this->holder = $holder;
		parent::__construct(1);
	}

	public function getFuelItem() : Item{
		return $this->getItem(self::SLOT_FUEL);
	}

	public function setFuelItem(Item $item) : void{
		$this->setItem(self::SLOT_FUEL, $item);
	}
}