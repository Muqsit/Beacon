<?php

declare(strict_types=1);

namespace muqsit\beacon\block\tile;

use Generator;
use InvalidArgumentException;
use muqsit\beacon\block\inventory\BeaconInventory;
use muqsit\beacon\block\tile\listener\BeaconChunkListener;
use muqsit\beacon\block\tile\listener\EmptyBeaconChunkListener;
use muqsit\beacon\block\tile\listener\SimpleBeaconChunkListener;
use muqsit\beacon\manager\BeaconManager;
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
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
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
	 * @return array{self::EFFECT_PRIMARY: Effect|null, self::EFFECT_SECONDARY: Effect|null}
	 */
	public static function readBeaconEffects(CompoundTag $nbt) : array{
		$map = EffectIdMap::getInstance();
		return [
			self::EFFECT_PRIMARY => $map->fromId($nbt->getInt(self::TAG_PRIMARY, 0)),
			self::EFFECT_SECONDARY => $map->fromId($nbt->getInt(self::TAG_SECONDARY, 0))
		];
	}

	public static function getLayerRequirementForBeaconEffect(int $type) : int{
		return match($type){
			self::EFFECT_PRIMARY => 1,
			self::EFFECT_SECONDARY => self::MAX_LAYERS,
			default => throw new InvalidArgumentException("Invalid beacon effect type {$type}")
		};
	}

	/** @var EffectInstance[]|null */
	private ?array $effects = null;

	protected ?BeaconInventory $inventory;
	private BeaconChunkListener $chunk_listener;
	private int $layers = 0;
	private bool $covered = false;
	private bool $recalculateLayers = true;
	private bool $recalculateCover = true;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new BeaconInventory($this->position);
		$this->chunk_listener = new SimpleBeaconChunkListener($this, self::MAX_LAYERS);
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

		$tag_layers = $nbt->getTag(self::TAG_LAYERS);
		if($tag_layers instanceof ByteTag){
			$this->layers = $tag_layers->getValue();
			$this->recalculateLayers = false;
		}else{
			$this->flagForLayerRecalculation();
		}

		$tag_covered = $nbt->getTag(self::TAG_COVERED);
		if($tag_covered instanceof ByteTag){
			$this->covered = (bool) $tag_covered->getValue();
			$this->recalculateCover = false;
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
		$world = $this->position->getWorld();
		foreach($this->getPyramidChunks() as [$chunkX, $chunkZ]){
			$world->registerChunkListener($this->chunk_listener, $chunkX, $chunkZ);
		}
	}

	protected function unregisterBeaconListener() : void{
		$world = $this->position->getWorld();
		foreach($this->getPyramidChunks() as [$chunkX, $chunkZ]){
			$world->unregisterChunkListener($this->chunk_listener, $chunkX, $chunkZ);
		}
		$this->chunk_listener = EmptyBeaconChunkListener::instance();
	}

	/**
	 * @param int $radius
	 * @return Generator<array{int, int}>
	 */
	protected function getPyramidChunks(int $radius = self::MAX_LAYERS) : Generator{
		$minChunkX = ($this->position->x - $radius) >> Chunk::COORD_BIT_SIZE;
		$maxChunkX = ($this->position->x + $radius) >> Chunk::COORD_BIT_SIZE;
		$minChunkZ = ($this->position->z - $radius) >> Chunk::COORD_BIT_SIZE;
		$maxChunkZ = ($this->position->z + $radius) >> Chunk::COORD_BIT_SIZE;
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

		$effects = [];
		$beacon_manager = BeaconManager::getInstance();
		foreach($this->effects as $beacon_effect_type => $effect){
			if($this->layers < self::getLayerRequirementForBeaconEffect($beacon_effect_type)){
				continue;
			}

			$type = $effect->getType();
			if(!$beacon_manager->isEffectValid($type, $this->layers)){
				continue;
			}

			$amplifier = $effect->getAmplifier();
			if(isset($effects[$effect_id = spl_object_id($type)]) && $amplifier <= $effects[$effect_id]->getAmplifier()){
				continue;
			}

			$effects[$effect_id] = new EffectInstance($type, $effect->getDuration(), $amplifier, $effect->isVisible(), $effect->isAmbient(), $effect->getColor());
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
			$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
		}
	}

	public function flagForRecalculation() : void{
		$this->flagForLayerRecalculation();
		$this->flagForCoverRecalculation();
	}

	public function flagForLayerRecalculation() : void{
		$this->recalculateLayers = true;
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
	}

	public function flagForCoverRecalculation() : void{
		$this->recalculateCover = true;
		$this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
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
		if($this->position->y < World::Y_MAX){
			if($this->position->y < 1){
				$this->covered = true;
				return;
			}

			$x = $this->position->x;
			$z = $this->position->z;

			$chunkX = $x >> Chunk::COORD_BIT_SIZE;
			$chunkZ = $z >> Chunk::COORD_BIT_SIZE;

			$world = $this->position->getWorld();
			$iterator = new SubChunkExplorer($world);
			$block_factory =  BlockFactory::getInstance();

			for($y = $this->position->y + 1; $y <= World::Y_MAX; ++$y){
				if(!$world->isChunkLoaded($chunkX, $chunkZ) || $iterator->moveTo($x, $y, $z) === SubChunkExplorerStatus::INVALID){
					continue;
				}

				if(!$block_factory->fromFullBlock($iterator->currentChunk->getFullBlock($x & SubChunk::COORD_MASK, $y, $z & SubChunk::COORD_MASK))->isTransparent()){
					$this->covered = true;
					return;
				}
			}
		}

		$this->covered = false;
	}

	public function recalculateLayers() : void{
		$this->layers = 0;
		$world = $this->position->getWorld();
		$iterator = new SubChunkExplorer($world);

		for($layer = 1; $layer < 5; ++$layer){
			$y = $this->position->y - $layer;

			if($y < 1){
				return;
			}

			$min_x = $this->position->x - $layer;
			$max_x = $this->position->x + $layer;

			$min_z = $this->position->z - $layer;
			$max_z = $this->position->z + $layer;

			$needed = ($n = $layer * 2 + 1) * $n;

			$beacon_manager = BeaconManager::getInstance();

			for($x = $min_x; $x <= $max_x; ++$x){
				for($z = $min_z; $z <= $max_z; ++$z){
					if(
						$world->isChunkLoaded($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE) &&
						$iterator->moveTo($x, $y, $z) !== SubChunkExplorerStatus::INVALID &&
						$beacon_manager->isFullBlockPyramidBlock($iterator->currentChunk->getFullBlock($x & SubChunk::COORD_MASK, $y, $z & SubChunk::COORD_MASK))
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
		if(count($effects) === 0){
			return;
		}

		$range = $this->getRange();

		$min_x = $this->position->x - $range;
		$max_x = $this->position->x + $range;

		$min_y = $this->position->y - $range;
		$max_y = $range + World::Y_MAX;

		$min_z = $this->position->z - $range;
		$max_z = $this->position->z + $range;

		$min_chunkX = $min_x >> Chunk::COORD_BIT_SIZE;
		$max_chunkX = $max_x >> Chunk::COORD_BIT_SIZE;

		$min_chunkZ = $min_z >> Chunk::COORD_BIT_SIZE;
		$max_chunkZ = $max_z >> Chunk::COORD_BIT_SIZE;

		$world = $this->position->getWorld();
		for($chunkX = $min_chunkX; $chunkX <= $max_chunkX; ++$chunkX){
			for($chunkZ = $min_chunkZ; $chunkZ <= $max_chunkZ; ++$chunkZ){
				foreach($world->getChunkEntities($chunkX, $chunkZ) as $entity){
					if(!($entity instanceof Player)){
						continue;
					}

					$pos = $entity->getPosition();
					if(
						$pos->x < $min_x || $pos->x > $max_x ||
						$pos->z < $min_z || $pos->z > $max_z ||
						$pos->y < $min_y || $pos->y > $max_y
					){
						continue;
					}

					foreach($effects as $effect){
						$entity->getEffects()->add(new EffectInstance($effect->getType(), $effect->getDuration(), $effect->getAmplifier(), $effect->isVisible(), $effect->isAmbient(), $effect->getColor()));
					}
				}
			}
		}

		$world->scheduleDelayedBlockUpdate($this->position, 80);
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$this->addNameSpawnData($nbt);
		$this->addBeaconEffectsData($nbt);
		$nbt->setString(self::TAG_ID, "Beacon");
	}

	public function close() : void{
		if(!$this->closed){
			$this->unregisterBeaconListener();
			$this->inventory = null;
		}

		parent::close();
	}
}