<?php

declare(strict_types=1);

namespace muqsit\beacon\block\tile\listener;

use muqsit\beacon\block\tile\Beacon;
use pocketmine\math\Vector3;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class SimpleBeaconChunkListener implements BeaconChunkListener{

	private World $world;
	private Vector3 $pos;
	private int $pyramid_radius;

	public function __construct(Beacon $beacon, int $pyramid_radius){
		$pos = $beacon->getPosition();
		$this->world = $pos->getWorld();
		$this->pos = $pos->asVector3();
		$this->pyramid_radius = $pyramid_radius;
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
		$depth = $this->pos->y - $block->y;
		if(
			$depth <= 0 ||
			$depth > $this->pyramid_radius ||
			$block->x < $this->pos->x - $depth ||
			$block->x > $this->pos->x + $depth ||
			$block->z < $this->pos->z - $depth ||
			$block->z > $this->pos->z + $depth
		){
			return;
		}

		$tile = $this->world->getTileAt($this->pos->x, $this->pos->y, $this->pos->z);
		if($tile instanceof Beacon){
			$tile->flagForLayerRecalculation();
		}
	}
}