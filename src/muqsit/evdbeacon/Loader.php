<?php

declare(strict_types=1);

namespace muqsit\evdbeacon;

use muqsit\evdbeacon\block\BlockFactory;
use pocketmine\plugin\PluginBase;

final class Loader extends PluginBase{

	protected function onLoad() : void{
		BlockFactory::init();
	}

	protected function onEnable() : void{
		new BeaconInventoryNetworkListener($this);
	}
}