<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\manager;

use pocketmine\entity\effect\Effect;

final class BeaconEffectValidator{

	public function __construct(
		public Effect $effect,
		public int $required_layers
	){}
}