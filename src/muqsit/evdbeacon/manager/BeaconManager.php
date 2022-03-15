<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\manager;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class BeaconManager{

	private static ?self $instance = null;

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
			new BeaconEffectValidator(VanillaEffects::SPEED(), 1),
			new BeaconEffectValidator(VanillaEffects::HASTE(), 1),
			new BeaconEffectValidator(VanillaEffects::RESISTANCE(), 2),
			new BeaconEffectValidator(VanillaEffects::JUMP_BOOST(), 2),
			new BeaconEffectValidator(VanillaEffects::STRENGTH(), 3),
			new BeaconEffectValidator(VanillaEffects::REGENERATION(), 4)
		]);
	}

	public static function getInstance() : self{
		return self::$instance ?? self::setInstance(self::vanilla());
	}

	public static function setInstance(self $instance) : self{
		return self::$instance = $instance;
	}

	/** @var int[] */
	private array $pyramid_blocks = [];

	/** @var int[] */
	private array $fuel_items = [];

	/** @var array<int, array<int, Effect>> */
	private array $effect_validators = [];

	/**
	 * @param Block[] $pyramid_blocks
	 * @param Item[] $fuel_items
	 * @param BeaconEffectValidator[] $effect_validators
	 */
	public function __construct(array $pyramid_blocks, array $fuel_items, array $effect_validators){
		foreach($pyramid_blocks as $block){
			$this->pyramid_blocks[$block_full_id = $block->getFullId()] = $block_full_id;
		}

		foreach($fuel_items as $item){
			$this->fuel_items[$item_id = $item->getId()] = $item_id;
		}

		foreach($effect_validators as $validator){
			$this->effect_validators[$validator->required_layers][spl_object_id($validator->effect)] = $validator->effect;
		}

		ksort($this->effect_validators);
	}

	public function isFullBlockPyramidBlock(int $full_id) : bool{
		return isset($this->pyramid_blocks[$full_id]);
	}

	public function isBlockPyramidBlock(Block $block) : bool{
		return $this->isFullBlockPyramidBlock($block->getFullId());
	}

	public function isFuelItem(Item $item) : bool{
		return isset($this->fuel_items[$item->getId()]);
	}

	public function isEffectValid(Effect $effect, int $layers) : bool{
		foreach($this->effect_validators as $required_layers => $effects){
			if($layers < $required_layers){
				break;
			}
			if(isset($effects[spl_object_id($effect)])){
				return true;
			}
		}

		return false;
	}
}