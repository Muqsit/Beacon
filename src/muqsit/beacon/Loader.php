<?php

declare(strict_types=1);

namespace muqsit\beacon;

use muqsit\beacon\block\BlockFactory;
use muqsit\beacon\timings\BeaconTimings;
use pocketmine\plugin\PluginBase;

final class Loader extends PluginBase{

	protected function onLoad() : void{
		BlockFactory::init();
	}

	protected function onEnable() : void{
		BeaconTimings::init($this);
		new BeaconInventoryNetworkListener($this);
	}
}