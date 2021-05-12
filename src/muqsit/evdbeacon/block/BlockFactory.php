<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block;

use muqsit\evdbeacon\block\tile\Beacon as BeaconTile;
use pocketmine\block\BlockFactory as VanillaBlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\VanillaBlocks;

final class BlockFactory {
    public static function init(): void {
        $parent = VanillaBlocks::BEACON();
        $parent_id_info = $parent->getIdInfo();
        VanillaBlockFactory::getInstance()->register(
            new Beacon(
                new BlockIdentifier(
                    $parent_id_info->getBlockId(),
                    $parent_id_info->getVariant(),
                    $parent_id_info->getItemId(),
                    BeaconTile::class
                ),
                $parent->getName(),
                $parent->getBreakInfo()
            ),
            true
        );

        TileFactory::getInstance()->register(BeaconTile::class, [
            'evdbeacon:beacon'
        ]);
    }
}
