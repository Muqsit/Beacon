<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\manager;

use Ds\Set;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class BeaconManager{

	/** @var self|null */
	private static $instance = null;

	public static function vanilla() : self{
		return new self([
			VanillaBlocks::COAL(),
			VanillaBlocks::DIAMOND(),
			VanillaBlocks::EMERALD(),
			VanillaBlocks::GOLD(),
			VanillaBlocks::IRON()
		], [
			VanillaItems::COAL(),
			VanillaItems::DIAMOND(),
			VanillaItems::EMERALD(),
			VanillaItems::GOLD_INGOT(),
			VanillaItems::IRON_INGOT()
		], [
			BeaconEffectValidator::create(VanillaEffects::SPEED(), 1),
			BeaconEffectValidator::create(VanillaEffects::HASTE(), 1),
			BeaconEffectValidator::create(VanillaEffects::RESISTANCE(), 2),
			BeaconEffectValidator::create(VanillaEffects::JUMP_BOOST(), 2),
			BeaconEffectValidator::create(VanillaEffects::STRENGTH(), 3),
			BeaconEffectValidator::create(VanillaEffects::REGENERATION(), 4)
		]);
	}

	public static function getInstance() : self{
		return self::$instance ?? self::setInstance(self::vanilla());
	}

	public static function setInstance(self $instance) : self{
		return self::$instance = $instance;
	}

	/** @var Set<int> */
	private $pyramid_blocks;

	/** @var Set<int> */
	private $fuel_items;

	/** @var array<int, Set<Effect>> */
	private $effect_validators = [];

	/**
	 * @param Block[] $pyramid_blocks
	 * @param Item[] $fuel_items
	 * @param BeaconEffectValidator[] $effect_validators
	 */
	public function __construct(array $pyramid_blocks, array $fuel_items, array $effect_validators){
		$this->pyramid_blocks = new Set();
		foreach($pyramid_blocks as $block){
			$this->pyramid_blocks->add($block->getFullId());
		}

		$this->fuel_items = new Set();
		foreach($fuel_items as $item){
			$this->fuel_items->add($item->getId());
		}

		foreach($effect_validators as $validator){
			if(!isset($this->effect_validators[$validator->required_layers])){
				$this->effect_validators[$validator->required_layers] = new Set();
			}
			$this->effect_validators[$validator->required_layers]->add($validator->effect);
		}

		ksort($this->effect_validators);
	}

	public function isFullBlockPyramidBlock(int $full_id) : bool{
		return $this->pyramid_blocks->contains($full_id);
	}

	public function isBlockPyramidBlock(Block $block) : bool{
		return $this->isFullBlockPyramidBlock($block->getFullId());
	}

	public function isFuelItem(Item $item) : bool{
		return $this->fuel_items->contains($item->getId());
	}

	public function isEffectValid(Effect $effect, int $layers) : bool{
		foreach($this->effect_validators as $required_layers => $effects){
			if($layers < $required_layers){
				break;
			}
			if($effects->contains($effect)){
				return true;
			}
		}

		return false;
	}
}