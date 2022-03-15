<?php

declare(strict_types=1);

namespace muqsit\beacon\timings;

use muqsit\beacon\Loader;
use pocketmine\timings\TimingsHandler;

final class BeaconTimings{

	public static TimingsHandler $tick;

	public static function init(Loader $loader) : void{
		$plugin_info = "Plugin: {$loader->getFullName()}";
		self::$tick = new TimingsHandler("{$plugin_info} Event: Beacon::tick");
	}
}