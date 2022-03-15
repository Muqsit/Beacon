<?php

declare(strict_types=1);

namespace muqsit\beacon\block\tile\listener;

use pocketmine\world\ChunkListenerNoOpTrait;

final class EmptyBeaconChunkListener implements BeaconChunkListener{
	use ChunkListenerNoOpTrait;

	public static function instance() : self{
		static $instance = null;
		return $instance ??= new self();
	}

	private function __construct(){
	}
}