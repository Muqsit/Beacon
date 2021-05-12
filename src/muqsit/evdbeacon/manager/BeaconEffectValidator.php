<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\manager;

use pocketmine\entity\effect\Effect;

final class BeaconEffectValidator {
    public static function create(Effect $effect, int $required_layers): self {
        return new self($effect, $required_layers);
    }

    public Effect $effect;
    public int $required_layers;

    private function __construct(Effect $effect, int $required_layers) {
        $this->effect = $effect;
        $this->required_layers = $required_layers;
    }
}
