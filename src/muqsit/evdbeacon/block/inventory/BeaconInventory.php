<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block\inventory;

use pocketmine\block\inventory\BlockInventory;
use pocketmine\item\Item;
use pocketmine\world\Position;

class BeaconInventory extends BlockInventory{

	public const SLOT_FUEL = 0;

	public function __construct(Position $holder){
		parent::__construct($holder, 1);
	}

	public function getFuelItem() : Item{
		return $this->getItem(self::SLOT_FUEL);
	}

	public function setFuelItem(Item $item) : void{
		$this->setItem(self::SLOT_FUEL, $item);
	}
}