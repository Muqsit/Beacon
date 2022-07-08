<?php

declare(strict_types=1);

namespace muqsit\beacon;

use muqsit\beacon\block\inventory\BeaconInventory;
use muqsit\beacon\block\tile\Beacon;
use muqsit\beacon\manager\BeaconManager;
use muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;

final class BeaconInventoryNetworkListener{

	public function __construct(Loader $plugin){
		SimplePacketHandler::createInterceptor($plugin)
			->interceptOutgoing(static function(ContainerOpenPacket $packet, NetworkSession $origin) : bool{
				if($origin->getInvManager()->getWindow($packet->windowId) instanceof BeaconInventory){
					$packet->windowType = WindowTypes::BEACON;
				}
				return true;
			})
			->interceptIncoming(static function(InventoryTransactionPacket $packet, NetworkSession $origin) : bool{
				if($packet->trData instanceof NormalTransactionData){
					foreach($packet->trData->getActions() as $action){
						if($action->inventorySlot === 27){
							$inv_manager = $origin->getInvManager();
							$window_id = $inv_manager->getCurrentWindowId();
							if($inv_manager->getWindow($window_id) instanceof BeaconInventory){
								$action->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
								$action->windowId = $window_id;
								$action->inventorySlot = BeaconInventory::SLOT_FUEL;
							}
						}
					}
				}
				return true;
			})
			->interceptIncoming(static function(BlockActorDataPacket $packet, NetworkSession $origin) : bool{
				$player = $origin->getPlayer();
				if($player === null){
					return true;
				}

				$pos = new Vector3($packet->blockPosition->getX(), $packet->blockPosition->getY(), $packet->blockPosition->getZ());
				$player_pos = $player->getPosition();
				if($pos->distanceSquared($player_pos) > 10000){
					return true;
				}

				$tile = $player_pos->getWorld()->getTile($pos);
				if(!($tile instanceof Beacon)){
					return true;
				}

				$inventory = $tile->getInventory();
				$fuel = $inventory->getFuelItem();
				if($fuel->isNull() || !BeaconManager::getInstance()->isFuelItem($fuel)){
					return true;
				}

				$nbt = $packet->nbt->getRoot();
				if(!($nbt instanceof CompoundTag)){
					return true;
				}

				[
					Beacon::EFFECT_PRIMARY => $primary_selected,
					Beacon::EFFECT_SECONDARY => $secondary_selected
				] = Beacon::readBeaconEffects($nbt);

				$beacon_manager = BeaconManager::getInstance();
				$layers = $tile->getLayers();

				if($primary_selected !== null && !$beacon_manager->isEffectValid($primary_selected, $layers)){
					return false;
				}

				if($secondary_selected !== null && !$beacon_manager->isEffectValid($secondary_selected, $layers)){
					return false;
				}

				if($primary_selected !== null && $secondary_selected !== null && $primary_selected !== $secondary_selected){
					return false;
				}

				$fuel->pop();
				$inventory->setFuelItem($fuel);
				$tile->setEffects($primary_selected, $secondary_selected);
				return true;
			});
	}
}
