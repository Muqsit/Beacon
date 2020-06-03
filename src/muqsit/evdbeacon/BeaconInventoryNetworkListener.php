<?php

declare(strict_types=1);

namespace muqsit\evdbeacon;

use Closure;
use muqsit\evdbeacon\block\inventory\BeaconInventory;
use muqsit\evdbeacon\block\tile\Beacon;
use muqsit\evdbeacon\manager\BeaconManager;
use muqsit\evdbeacon\utils\ClosureSignatureParser;
use pocketmine\entity\effect\Effect;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ContainerOpenPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use pocketmine\Server;

final class BeaconInventoryNetworkListener implements Listener{

	/**
	 * @var Closure[][]
	 * @phpstan-var array<int, array<Closure(ServerboundPacket, NetworkSession) : bool>>
	 */
	private $incoming_handlers = [];

	/**
	 * @var Closure[][]
	 * @phpstan-var array<int, array<Closure(ClientboundPacket, NetworkSession) : bool>>
	 */
	private $outgoing_handlers = [];

	public function __construct(){
		$this->handleOutgoing(static function(ContainerOpenPacket $packet, NetworkSession $origin) : bool{
			if($origin->getInvManager()->getWindow($packet->windowId) instanceof BeaconInventory){
				$packet->type = WindowTypes::BEACON;
			}
			return true;
		});

		$this->handleIncoming(static function(InventoryTransactionPacket $packet, NetworkSession $origin) : bool{
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
		});

		$this->handleIncoming(static function(BlockActorDataPacket $packet, NetworkSession $origin) : bool{
			$player = $origin->getPlayer();
			if($player instanceof Player){
				$pos = new Vector3($packet->x, $packet->y, $packet->z);
				$player_pos = $player->getPosition();
				if($pos->distanceSquared($player_pos) > 10000){
					return false;
				}

				$tile = $player_pos->getWorldNonNull()->getTile($pos);
				if($tile instanceof Beacon){
					$inventory = $tile->getInventory();
					$fuel = $inventory->getFuelItem();
					if(!$fuel->isNull() && BeaconManager::getInstance()->isFuelItem($fuel)){
						$nbt = $packet->namedtag->getRoot();
						if($nbt instanceof CompoundTag){
							/**
							 * @var Effect|null $primary_selected
							 * @var Effect|null $secondary_selected
							 */
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
						}
					}
				}
			}

			return true;
		});
	}

	/**
	 * @param Closure $handler
	 *
	 * @phpstan-template TServerboundPacket of ServerboundPacket
	 * @phpstan-param Closure(TServerboundPacket, NetworkSession) : bool $handler
	 */
	private function handleIncoming(Closure $handler) : void{
		$classes = ClosureSignatureParser::parse($handler, [ServerboundPacket::class, NetworkSession::class], "bool");
		assert(is_a($classes[0], DataPacket::class, true));
		$this->incoming_handlers[$classes[0]::NETWORK_ID][spl_object_id($handler)] = $handler;
	}

	/**
	 * @param Closure $handler
	 *
	 * @phpstan-template TClientboundPacket of ClientboundPacket
	 * @phpstan-param Closure(TClientboundPacket, NetworkSession) : bool $handler
	 */
	private function handleOutgoing(Closure $handler) : void{
		$classes = ClosureSignatureParser::parse($handler, [ClientboundPacket::class, NetworkSession::class], "bool");
		assert(is_a($classes[0], DataPacket::class, true));
		$this->outgoing_handlers[$classes[0]::NETWORK_ID][spl_object_id($handler)] = $handler;
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 * @priority NORMAL
	 */
	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void{
		/** @var DataPacket|ServerboundPacket $packet */
		$packet = $event->getPacket();
		if(isset($this->incoming_handlers[$pid = $packet::NETWORK_ID])){
			$origin = $event->getOrigin();
			foreach($this->incoming_handlers[$pid] as $handler){
				if(!$handler($packet, $origin)){
					$event->setCancelled();
					break;
				}
			}
		}
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority NORMAL
	 */
	public function onDataPacketSend(DataPacketSendEvent $event) : void{
		$packets = $event->getPackets();
		/** @var DataPacket|ClientboundPacket $packet */
		foreach($packets as $packet){
			if(isset($this->outgoing_handlers[$pid = $packet::NETWORK_ID])){
				$current_targets = $event->getTargets();
				foreach($current_targets as $index => $target){
					foreach($this->outgoing_handlers[$pid] as $handler){
						if(!$handler($packet, $target)){
							$event->setCancelled();

							$new_targets = $current_targets;
							unset($new_targets[$index]);
							if(count($new_targets) > 0){
								$new_target_players = [];
								foreach($new_targets as $new_target){
									$new_target_player = $new_target->getPlayer();
									if($new_target_player !== null){
										$new_target_players[] = $new_target_player;
									}
								}
								Server::getInstance()->broadcastPackets($new_target_players, $packets);
							}
							break;
						}
					}
				}
			}
		}
	}
}