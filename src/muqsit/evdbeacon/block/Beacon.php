<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block;

use muqsit\evdbeacon\block\tile\Beacon as BeaconTile;
use muqsit\evdbeacon\timings\BeaconTimings;
use pocketmine\block\Block;
use pocketmine\block\Transparent;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class Beacon extends Transparent{

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		return $face !== Facing::DOWN && parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function getLightLevel() : int{
		return 15;
	}

	public function getBeaconTile() : ?BeaconTile{
		$tile = $this->pos->getWorld()->getTileAt($this->pos->x, $this->pos->y, $this->pos->z);
		return $tile instanceof BeaconTile ? $tile : null;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player instanceof Player){
			$tile = $this->getBeaconTile();
			if($tile instanceof BeaconTile){
				return $player->setCurrentWindow($tile->getInventory());
			}
		}

		return parent::onInteract($item, $face, $clickVector, $player);
	}

	public function onScheduledUpdate() : void{
		$tile = $this->getBeaconTile();
		if($tile instanceof BeaconTile){
			BeaconTimings::$tick->startTiming();
			$tile->tick();
			BeaconTimings::$tick->stopTiming();
		}
	}
}