<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block\tile;

use Generator;
use InvalidArgumentException;
use muqsit\evdbeacon\block\inventory\BeaconInventory;
use muqsit\evdbeacon\manager\BeaconManager;
use pocketmine\block\BlockFactory;
use pocketmine\block\tile\ContainerTrait;
use pocketmine\block\tile\Nameable;
use pocketmine\block\tile\NameableTrait;
use pocketmine\block\tile\Spawnable;
use pocketmine\data\bedrock\EffectIdMap;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\inventory\InventoryHolder;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\utils\SubChunkExplorer;
use pocketmine\world\utils\SubChunkExplorerStatus;
use pocketmine\world\World;

class Beacon extends Spawnable implements InventoryHolder, Nameable{
	use NameableTrait{
		addAdditionalSpawnData as addNameSpawnData;
	}
	use ContainerTrait{
		onBlockDestroyedHook as containerTraitBlockDestroyedHook;
	}

	public const TAG_PRIMARY = "primary";
	public const TAG_SECONDARY = "secondary";
	public const TAG_LAYERS = "layers";
	public const TAG_COVERED = "covers";

	public const MAX_LAYERS = 4;

	public const EFFECT_PRIMARY = 0;
	public const EFFECT_SECONDARY = 1;

	/**
	 * @param CompoundTag $nbt
	 * @return Effect[]|null[]
	 *
	 * @phpstan-return array<int, Effect|null>
	 */
	public static function readBeaconEffects(CompoundTag $nbt) : array{
		$map = EffectIdMap::getInstance();
		return [
			self::EFFECT_PRIMARY => $map->fromId($nbt->getInt(self::TAG_PRIMARY, 0)),
			self::EFFECT_SECONDARY => $map->fromId($nbt->getInt(self::TAG_SECONDARY, 0))
		];
	}

	public static function getLayerRequirementForBeaconEffect(int $type) : int{
		switch($type){
			case self::EFFECT_PRIMARY:
				return 1;
			case self::EFFECT_SECONDARY:
				return self::MAX_LAYERS;
		}

		throw new InvalidArgumentException("Invalid beacon effect type {$type}");
	}

	/** @var EffectInstance[]|null */
	private ?array $effects = null;

	protected BeaconInventory $inventory;
	private BeaconChunkListener $chunk_listener;
	private int $layers = 0;
	private bool $covered = false;
	private bool $recalculateLayers = true;
	private bool $recalculateCover = true;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new BeaconInventory($this->pos);
		$this->chunk_listener = new BeaconChunkListener($this);
		$this->registerBeaconListener();
	}

	public function getInventory() : BeaconInventory{
		return $this->inventory;
	}

	public function getRealInventory() : BeaconInventory{
		return $this->inventory;
	}

	public function getDefaultName() : string{
		return "Beacon";
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->loadName($nbt);
		$this->loadItems($nbt);

		$effects = self::readBeaconEffects($nbt);
		$this->setEffects($effects[self::EFFECT_PRIMARY], $effects[self::EFFECT_SECONDARY]);

		if($nbt->hasTag(self::TAG_LAYERS)){
			$this->layers = $nbt->getByte(self::TAG_LAYERS);
		}else{
			$this->flagForLayerRecalculation();
		}

		if($nbt->hasTag(self::TAG_COVERED)){
			$this->covered = (bool) $nbt->getByte(self::TAG_COVERED);
		}else{
			$this->flagForCoverRecalculation();
		}
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$this->saveName($nbt);
		$this->saveItems($nbt);

		$this->addBeaconEffectsData($nbt);

		if(!$this->recalculateLayers){
			$nbt->setByte(self::TAG_LAYERS, $this->layers);
		}

		if(!$this->recalculateCover){
			$nbt->setByte(self::TAG_COVERED, (int) $this->covered);
		}
	}

	protected function addBeaconEffectsData(CompoundTag $nbt) : void{
		$map = EffectIdMap::getInstance();
		$nbt->setInt(self::TAG_PRIMARY, isset($this->effects[self::EFFECT_PRIMARY]) ? $map->toId($this->effects[self::EFFECT_PRIMARY]->getType()) : 0);
		$nbt->setInt(self::TAG_SECONDARY, isset($this->effects[self::EFFECT_SECONDARY]) ? $map->toId($this->effects[self::EFFECT_SECONDARY]->getType()) : 0);
	}

	protected function onBlockDestroyedHook() : void{
		parent::onBlockDestroyedHook();
		$this->containerTraitBlockDestroyedHook();
	}

	protected function registerBeaconListener() : void{
		$world = $this->pos->getWorld();
		foreach($this->getPyramidChunks() as [$chunkX, $chunkZ]){
			$world->registerChunkListener($this->chunk_listener, $chunkX, $chunkZ);
		}
	}

	protected function unregisterBeaconListener() : void{
		$world = $this->pos->getWorld();
		foreach($this->getPyramidChunks() as [$chunkX, $chunkZ]){
			$world->unregisterChunkListener($this->chunk_listener, $chunkX, $chunkZ);
		}
	}

	/**
	 * @return Generator<int[]>
	 */
	protected function getPyramidChunks() : Generator{
		$minChunkX = ($this->pos->x - 4) >> 4;
		$maxChunkX = ($this->pos->x + 4) >> 4;
		$minChunkZ = ($this->pos->z - 4) >> 4;
		$maxChunkZ = ($this->pos->z + 4) >> 4;
		for($chunkX = $minChunkX; $chunkX <= $maxChunkX; ++$chunkX){
			for($chunkZ = $minChunkZ; $chunkZ <= $maxChunkZ; ++$chunkZ){
				yield [$chunkX, $chunkZ];
			}
		}
	}

	public function getLayers() : int{
		return $this->layers;
	}

	public function getRange() : int{
		return ($this->layers + 1) * 10;
	}

	public function getEffectDuration() : int{
		return (9 + ($this->layers * 2)) * 20;
	}

	public function hasEffects() : bool{
		return $this->effects !== null;
	}

	/**
	 * @return EffectInstance[]
	 */
	public function getValidEffects() : array{
		if($this->effects === null){
			return [];
		}

		/** @var EffectInstance[] $effects */
		$effects = [];
		$beacon_manager = BeaconManager::getInstance();
		foreach($this->effects as $beacon_effect_type => $effect){
			if($this->layers >= self::getLayerRequirementForBeaconEffect($beacon_effect_type)){
				$type = $effect->getType();
				if($beacon_manager->isEffectValid($type, $this->layers)){
					$amplifier = $effect->getAmplifier();
					if(!isset($effects[$runtime_id = $type->getRuntimeId()]) || $amplifier > $effects[$runtime_id]->getAmplifier()){
						$effects[$runtime_id] = new EffectInstance($type, $effect->getDuration(), $amplifier, $effect->isVisible(), $effect->isAmbient(), $effect->getColor());
					}
				}
			}
		}
		return $effects;
	}

	public function setEffects(?Effect $primary, ?Effect $secondary) : void{
		$effects = [];

		if($primary !== null){
			$effects[self::EFFECT_PRIMARY] = new EffectInstance($primary, $this->getEffectDuration(), 0, true, true);
		}

		if($secondary !== null){
			$effects[self::EFFECT_SECONDARY] = new EffectInstance($secondary, $this->getEffectDuration(), 1, true, true);
		}

		$this->effects = count($effects) > 0 ? $effects : null;

		if($this->hasEffects()){
			$this->pos->getWorld()->scheduleDelayedBlockUpdate($this->pos, 1);
		}
	}

	public function flagForRecalculation() : void{
		$this->flagForLayerRecalculation();
		$this->flagForCoverRecalculation();
	}

	public function flagForLayerRecalculation() : void{
		$this->recalculateLayers = true;
		$this->pos->getWorld()->scheduleDelayedBlockUpdate($this->pos, 1);
	}

	public function flagForCoverRecalculation() : void{
		$this->recalculateCover = true;
		$this->pos->getWorld()->scheduleDelayedBlockUpdate($this->pos, 1);
	}

	public function doRecalculationChecks() : void{
		if($this->recalculateLayers){
			$this->recalculateLayers = false;
			$this->recalculateLayers();
		}

		if($this->recalculateCover){
			$this->recalculateCover = false;
			$this->recalculateCover();
		}
	}

	public function recalculateCover() : void{
		if($this->pos->y < World::Y_MAX){
			if($this->pos->y < 1){
				$this->covered = true;
				return;
			}

			$x = $this->pos->x;
			$z = $this->pos->z;

			$chunkX = $x >> 4;
			$chunkZ = $z >> 4;

			$world = $this->pos->getWorld();
			$iterator = new SubChunkExplorer($world);
			$block_factory =  BlockFactory::getInstance();

			for($y = $this->pos->y + 1; $y <= World::Y_MAX; ++$y){
				if(!$world->isChunkLoaded($chunkX, $chunkZ) || $iterator->moveTo($x, $y, $z) === SubChunkExplorerStatus::INVALID){
					continue;
				}

				if(!$block_factory->fromFullBlock($iterator->currentChunk->getFullBlock($x & 0x0f, $y, $z & 0x0f))->isTransparent()){
					$this->covered = true;
					return;
				}
			}
		}

		$this->covered = false;
	}

	public function recalculateLayers() : void{
		$this->layers = 0;
		$world = $this->pos->getWorld();
		$iterator = new SubChunkExplorer($world);

		for($layer = 1; $layer < 5; ++$layer){
			$y = $this->pos->y - $layer;

			if($y < 1){
				return;
			}

			$min_x = $this->pos->x - $layer;
			$max_x = $this->pos->x + $layer;

			$min_z = $this->pos->z - $layer;
			$max_z = $this->pos->z + $layer;

			$needed = ($n = $layer * 2 + 1) * $n;

			$beacon_manager = BeaconManager::getInstance();

			for($x = $min_x; $x <= $max_x; ++$x){
				for($z = $min_z; $z <= $max_z; ++$z){
					if(
						$world->isChunkLoaded($x >> 4, $z >> 4) &&
						$iterator->moveTo($x, $y, $z) !== SubChunkExplorerStatus::INVALID &&
						$beacon_manager->isFullBlockPyramidBlock($iterator->currentChunk->getFullBlock($x & 0x0f, $y, $z & 0x0f))
					){
						--$needed;
					}
				}
			}

			if($needed !== 0){
				return;
			}

			++$this->layers;
		}
	}

	public function tick() : void{
		$this->doRecalculationChecks();
		if($this->covered || $this->layers === 0 || $this->effects === null){
			return;
		}

		$effects = $this->getValidEffects();
		if(count($effects) > 0){
			$range = $this->getRange();

			$min_x = $this->pos->x - $range;
			$max_x = $this->pos->x + $range;

			$min_y = $this->pos->y - $range;
			$max_y = $range + World::Y_MAX;

			$min_z = $this->pos->z - $range;
			$max_z = $this->pos->z + $range;

			$min_chunkX = $min_x >> 4;
			$max_chunkX = $max_x >> 4;

			$min_chunkZ = $min_z >> 4;
			$max_chunkZ = $max_z >> 4;

			$world = $this->pos->getWorld();
			for($chunkX = $min_chunkX; $chunkX <= $max_chunkX; ++$chunkX){
				for($chunkZ = $min_chunkZ; $chunkZ <= $max_chunkZ; ++$chunkZ){
					$chunk = $world->getChunk($chunkX, $chunkZ);
					if($chunk !== null){
						foreach($chunk->getEntities() as $entity){
							if($entity instanceof Player){
								$pos = $entity->getPosition();
								if(
									$pos->x >= $min_x && $pos->x <= $max_x &&
									$pos->z >= $min_z && $pos->z <= $max_z &&
									$pos->y >= $min_y && $pos->y <= $max_y
								){
									foreach($effects as $effect){
										$entity->getEffects()->add(new EffectInstance($effect->getType(), $effect->getDuration(), $effect->getAmplifier(), $effect->isVisible(), $effect->isAmbient(), $effect->getColor()));
									}
								}
							}
						}
					}
				}
			}

			$world->scheduleDelayedBlockUpdate($this->pos, 80);
		}
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->addNameSpawnData($nbt);
		$this->addBeaconEffectsData($nbt);
		$nbt->setString(self::TAG_ID, "Beacon");
	}

	public function close() : void{
		if(!$this->closed){
			$this->unregisterBeaconListener();
		}

		parent::close();
	}
}