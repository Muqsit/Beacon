<?php

declare(strict_types=1);

namespace muqsit\evdbeacon\block\tile\listener;

use muqsit\evdbeacon\block\tile\Beacon;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class SimpleBeaconChunkListener implements BeaconChunkListener{

	protected World $world;
	protected Vector3 $pos;

	public function __construct(Beacon $beacon){
		$pos = $beacon->getPos();
		$this->world = $pos->getWorld();
		$this->pos = $pos->asVector3();
	}

	public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk) : void{
	}

	public function onChunkLoaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{
	}

	public function onChunkUnloaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{
	}

	public function onChunkPopulated(int $chunkX, int $chunkZ, Chunk $chunk) : void{
	}

	public function onBlockChanged(Vector3 $block) : void{
		if( // TODO: Check for blocks inside 3D pyramidal volume instead of a cubical, possibly by caching XYZ offsets relative to beacon's position
			$block->y >= ($this->pos->y - 4) &&
			$block->y < $this->pos->y &&

			$block->x >= ($this->pos->x - 4) &&
			$block->x <= ($this->pos->x + 4) &&

			$block->z >= ($this->pos->z - 4) &&
			$block->z <= ($this->pos->z + 4)
		){
			$tile = $this->world->getTileAt($this->pos->x, $this->pos->y, $this->pos->z);
			if($tile instanceof Beacon){
				$tile->flagForLayerRecalculation();
			}
		}
	}
}