<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\timings;

use muqsit\evdbeacon\Loader;
use pocketmine\timings\TimingsHandler;

final class BeaconTimings {
    public static TimingsHandler $tick;

    public static function init(Loader $loader): void {
        $plugin_info = "Plugin: {$loader->getFullName()}";
        self::$tick = new TimingsHandler("{$plugin_info} Event: Beacon::tick");
    }
}
