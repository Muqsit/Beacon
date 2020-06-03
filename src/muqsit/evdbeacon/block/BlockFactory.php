<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block;

use muqsit\evdbeacon\block\tile\Beacon as BeaconTile;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory as VanillaBlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockToolType;
use pocketmine\block\tile\TileFactory;
use pocketmine\item\ItemIds;

final class BlockFactory{

	public static function init() : void{
		VanillaBlockFactory::getInstance()->register(new Beacon(new BlockIdentifier(BlockLegacyIds::BEACON, 0, ItemIds::BEACON, BeaconTile::class), "Beacon", new BlockBreakInfo(0.5, BlockToolType::PICKAXE, 0, 80.0)));
		TileFactory::getInstance()->register(BeaconTile::class, ["evdbeacon:beacon"]);
	}
}