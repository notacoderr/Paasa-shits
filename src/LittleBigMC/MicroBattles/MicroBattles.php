<?php
#BY CODEEEH AND LENTO
namespace LittleBigMC\MicroBattles;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\event\Listener;
use pocketmine\event\player\{
	PlayerInteractEvent, PlayerDropItem, PlayerJoinEvent, PlayerQuitEvent
};
use pocketmine\command\{
	CommandSender, Command
};
use pocketmine\utils\{
	TextFormat, Config, Color
};
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\event\block\{
	BlockBreakEvent, BlockPlaceEvent, PlayerMoveEvent, PlayerDeathEvent
};
use pocketmine\event\entity\{
	EntityDamageEvent, EntityDamageByEntityEvent, EntityLevelChangeEvent, EntityShootBowEvent
};
use pocketmine\tile\Chest;
use pocketmine\inventory\ChestInventory;
use onebone\economyapi\EconomyAPI;
use LittleBigMC\MicroBattles\{
	Resetmap, RefreshArena
};

class MicroBattles extends PluginBase implements Listener
{

	public $prefix = TextFormat::BOLD . TextFormat::DARK_GRAY . "[" . TextFormat::AQUA . "Micro" . TextFormat::GREEN . "Battles" . TextFormat::DARK_GRAY . "]" . TextFormat::RESET . TextFormat::GRAY;
	public $mode = 0;
	public $currentLevel = "";
	
	/*
	ARENA STAGES
	1 - playing
	2 - waiting / protected
	3 - restricted
	*/            
	
	public $arenas = [], $arenastatus = [], $arenaplayers = [], $team = [];
	
	public function onEnable() : void
	{
		$this->getLogger()->info($this->prefix);
		$this->getServer()->getPluginManager()->registerEvents($this ,$this);
		$this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");

		if(!empty($this->economy))
		{
			$this->api = EconomyAPI::getInstance();
		}

		@mkdir($this->getDataFolder());
		$config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		
		//$this->saveResource("items.yml");
		//$itemconf = new Config($this->getDataFolder() . "items.yml", CONFIG::YAML);

		if( $config->get("arenas") != null )
		{
			$this->arenas = $config->get("arenas");
		}

		foreach($this->arenas as $lev)
		{
			$this->getServer()->loadLevel($lev);
			$this->arenaplayers[$lev] = [];
			$this->arenastatus[$lev] = 2;
		}
		//$itemconf->getAll(); 
		$items = array(
			array(1,0,30),
			array(1,0,20),
			array(3,0,15),
			array(3,0,25),
			array(4,0,35),
			array(4,0,15),
			array(260,0,5),
			array(261,0,1),
			array(262,0,5),
			array(267,0,1),
			array(268,0,1),
			array(272,0,1),
			array(276,0,1),
			array(283,0,1),
			array(297,0,3),
			array(298,0,1),
			array(299,0,1),
			array(300,0,1),
			array(301,0,1),
			array(303,0,1),
			array(304,0,1),
			array(310,0,1),
			array(313,0,1),
			array(314,0,1),
			array(315,0,1),
			array(316,0,1),
			array(317,0,1),
			array(320,0,4),
			array(354,0,1),
			array(364,0,4),
			array(366,0,5),
			array(391,0,5)
		);

		if( $config->get("chestitems") == null )
		{
			$config->set( "chestitems" , $items);
		}

		$config->save();
		$this->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
		$this->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 10);

	}

	function getZip()
	{
 		return new RefreshArena($this);
	}
	
	function onJoin(PlayerJoinEvent $event)
	{
 		$player = $event->getPlayer();
 		if(in_array($player->getLevel()->getFolderName(), $this->arenas))
 		{
  			$this->leaveArena($player);
 		}
	}

	function onQuit(PlayerQuitEvent $event) : void
	{
		$player = $event->getPlayer();
		if(in_array($player->getLevel()->getFolderName(), $this->arenas))
		{
			$this->leaveArena($player);
		}
	}
	
	function getStatus(string $levelname) : int
	{
		if(array_key_exists($levename, $this->arenastatus))
		{
			return $this->arenastatus[$levelname];
		}
		return 0;
	}
	
	function setStage(string $levelname, int $stage) : void
	{
		$this->arenastatus[$levelname] = $stage;
	}

	function getTeam(Player $player) : string
	{
		if(array_key_exists($player->getUniqueId(), $this->team))
		{
			$this->team[$player->getUniqueId()];
		} else {
			return "";
		}
	}
	
	function setSpectator(Player $player) : void
	{
		$this->team[$player->getUniqueId()] = "spectator";
		$player->setGamemode(3);
		$player->sendMessage("§lYou are a spectator.§r §7use /mb leave to exit.");
	}

	function onMove(PlayerMoveEvent $event) : void
	{
	 	$level = $event->getPlayer()->getLevel()->getFolderName();
	 	if(in_array($level, $this->arenas))
	 	{
			if($this->getStatus($level) == 3)
			{
				$to = clone $event->getFrom();
				$to->yaw = $event->getTo()->yaw;
				$to->pitch = $event->getTo()->pitch;
				$event->setTo($to);
			}
	 	}
	}

	function onShoot(EntityShootBowEvent $event) : void
	{
 		$level = $event->getEntity()->getLevel()->getFolderName(); 
 		if($event->getEntity() instanceof Player && in_array($level, $this->arenas))
 		{
			switch($this->getStatus($level))
			{
				case 2:
					$event->setCancelled(true);
				break;
				default:
					$event->setCancelled(false);
			}
 		}
	}
	
	function onBlockBreak(BlockBreakEvent $event) : void
	{
		$level = $event->getPlayer()->getLevel()->getFolderName(); 
		if(in_array($level,$this->arenas))
		{
			switch($this->getStatus($level))
			{
				case 2: case 3:
					$event->setCancelled(true);
				break;
				default:
					$event->setCancelled(false);
			}
		}
	}
	
	public function onBlockPlace(BlockPlaceEvent $event) : void
	{
		$level = $event->getPlayer()->getLevel()->getFolderName(); 
		if(in_array($level,$this->arenas))
		{
			switch($this->getStatus($level))
			{
				case 2: case 3:
					$event->setCancelled(true);
				break;
				default:
					$event->setCancelled(false);
			}
		}
	}
	
	public function onDamage(EntityDamageEvent $event) : void
	{
		if($event instanceof EntityDamageByEntityEvent)
		{
			if($event->getEntity() instanceof Player && $event->getDamager() instanceof Player)
			{
				$level = $event->getEntity()->getLevel()->getFolderName();
				if(in_array($level, $this->arenas))
				{
					$victim = $event->getEntity();
					$killer = $event->getDamager();
					if($this->getStatus($level) == 1)
					{
						if($this->getTeam($victim) == $this->getTeam($killer)
						{
							$event->setCancelled();
						} else  {
							if($event->getDamage() >= $event->getEntity()->getHealth())
							{
								$event->setCancelled();
								$this->setSpectator($victim);
								$message = "§f". $killer->getDisplayName(). " §c==§f|§c=======> §f". $victim->getDisplayName();
								Server::getInstance()->broadcastMessage($message, $victim->getLevel()->getPlayers());
							}
						} 
					}
				}
			}
		} else {
			if($event->getEntity() instanceof Player)
			{
				$level = $event->getEntity()->getLevel()->getFolderName();
				if(in_array($level, $this->arenas))
				{
					if($this->getStatus($level) <> 1)
					{
						$event->setCancelled(true);
					}
				}
			}
		}
	}

	public function onCommand(CommandSender $player, Command $cmd, $label, array $args) : bool
	{
		if($player instanceof Player)
		{
			switch($cmd->getName())
			{
				case "mb":
					if(!empty($args[0]))
					{
						if($args[0]=='make' or $args[0]=='create')
						{
							if($player->isOp())
							{
									if(!empty($args[1]))
									{
										if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1]))
										{
											$this->getServer()->loadLevel($args[1]);
											$this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
											array_push($this->arenas, $args[1]);
											$this->currentLevel = $args[1];
											$this->mode = 1;
											$player->sendMessage($this->prefix . " Touch to set player spawns");
											$player->setGamemode(1);
											$player->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
											$name = $args[1];
											$this->getZip()->zip($player, $name);
											return true;
										} else {
											$player->sendMessage($this->prefix . " ERROR missing world.");
											return true;
										}
									}
									else
									{
										$player->sendMessage($this->prefix . " ERROR missing parameters.");
										return true;
									}
							} else {
								$player->sendMessage($this->prefix . " Oh no! You are not OP.");
								return true;
							}
						}
						else if($args[0] == "leave" or $args[0]=="quit" )
						{
							$level = $player->getLevel()->getFolderName();
							if(in_array($level, $this->arenas))
							{
								$this->leaveArena($player); 
								return true;
							}
						} else {
							$player->sendMessage($this->prefix . " Invalid command.");
							return true;
						}
					} else {
						$player->sendMessage($this->prefix . " /mb <make-leave> : Create Arena | Leave the game");
						$player->sendMessage($this->prefix . " /mbstart : Start the game in 10 seconds");
					}
					return true;
	
				case "mbstart":
				if($player->isOp())
				{
					$player->sendMessage($this->prefix . " §aStarting in 10 seconds...");
					$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
					$config->set("arenas",$this->arenas);
					foreach($this->arenas as $arena)
					{
						$config->set($arena . "PlayTime", 780);
						$config->set($arena . "StartTime", 11);
					}
					$config->save();
				}
				return true;
			}
		} 
	}

	public function leaveArena(Player $player) : void
	{
		$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
		Server::getInstance()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
		$player->teleport($spawn , 0, 0);
		$player->setFood(20);
		$player->setHealth(20);
		
		if(array_key_exists($player->getUniqueId(), $this->team))
		{
			$this->team[ $player->getUniqueId() ];
		}
		
		$this->cleanPlayer($player);
	}

	function onTeleport(EntityLevelChangeEvent $event) : void
	{
        if ($event->getEntity() instanceof Player) 
		{
			$player = $event->getEntity();
			$from = $event->getOrigin()->getFolderName();
			$to = $event->getTarget()->getFolderName();
			if(in_array($from, $this->arenas) && !in_array($to, $this->arenas))
			{
				$this->leaveArena($player);
			}
		
			if(in_array($to, $this->arenas))
			{
				$this->setSpectator($player);
			}
        }
	}
	
	function cleanPlayer(Player $player) : void
	{
		$player->getInventory()->clearAll();
		$player->getArmorInventory()->clearAll();
		$player->setNameTag( $this->getServer()->getPluginManager()->getPlugin('PureChat')->getNametag($player) );
	}
	
	public function assignTeam($arena)
	{
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$level = Server::getInstance()->getLevelByName($arena);
		$players = $this->arenaplayers[$arena];//$level->getAllPlayers();
		shuffle($players);
		$i = 1;
		foreach($players as $player)
		{
			if(!array_key_exists($player->getUniqueId(), $this->team))
			{
				switch($i)
				{
					case 1: case 2: case 3:
						$thespawn = $config->get($arena . "_red_center_spawn"); $this->joinTeam($player, $i, "red");
					break;
					case 4: case 5: case 6:
						$thespawn = $config->get($arena . "_blue_center_spawn"); $this->joinTeam($player, $i, "blue");
					break;
					case 7: case 8: case 9: 
						$thespawn = $config->get($arena . "_green_center_spawn"); $this->joinTeam($player, $i, "green");
					break;
					case 10: case 11: case 12:
						$thespawn = $config->get($arena . "_yellow_center_spawn"); $this->joinTeam($player, $i, "yellow");
					break;
				}
				$spawn = new Position($thespawn[0] + 0.5 , $thespawn[1] , $thespawn[2] + 0.5 , $level);
				$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
				$player->teleport($spawn, 0, 0);
				$player->setHealth(20);
				$player->setGameMode(0);
				$i += 1;
			}
		}
	}
	
	private function joinTeam(Player $player, string $i, string $color)
	{
		switch($i)
		{
			case 1: case 2: case 3:
				$this->team[$player->getUniqueId()] = $color;
				$this->sendColors($player, $color);
				$player->setNameTag("§l§c[RED]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§c[RED]");
				$player->sendMessage('§b/mb quit §7- to leave the arena');
				break;
							
			case 4: case 5: case 6:
				$this->team[$player->getUniqueId()] = $color;
				$this->sendColors($player, $color);
				$player->setNameTag("§l§9[BLUE]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§9[BLUE]");
				$player->sendMessage('§b/mb quit §7- to leave the arena');
				break;
							
			case 7: case 8: case 9:
				$this->team[$player->getUniqueId()] = $color;
				$this->sendColors($player, $color);
				$player->setNameTag("§l§a[GREEN]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§a[GREEN]");
				$player->sendMessage('§b/mb quit §7- to leave the arena');
				break;
							
			case 10: case 11: case 12:
				$this->team[$player->getUniqueId()] = $color;
				$this->sendColors($player, $color);
				$player->setNameTag("§l§e[YELLOW]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§e[YELLOW]");		
				$player->sendMessage('§b/mb quit §7- to leave the arena');
				break;
							
			default:
				$player->sendMessage($this->prefix . " You can't join");
		}
		$player->getInventory()->setItem(0, Item::get(339, 69, 1)->setCustomName('§r§l§fClass Picker'));
		$player->getInventory()->setItem(8, Item::get(339, 666, 1)->setCustomName('§r§l§fTap to leave'));
		
	}
	
	public function sendColors(Player $player, string $color)
	{
		$a = Item::get(Item::LEATHER_CAP);
		$b = Item::get(Item::LEATHER_TUNIC);
		$c = Item::get(Item::LEATHER_PANTS);
		$d = Item::get(Item::LEATHER_BOOTS);
		switch($color)
		{
			case 'red':
				$a->setCustomColor(new Color(255,0,0));
				$b->setCustomColor(new Color(255,0,0));
				$c->setCustomColor(new Color(255,0,0));
				$d->setCustomColor(new Color(255,0,0));
			break;
			
			case 'blue':
				$a->setCustomColor(new Color(0,0,255));
				$b->setCustomColor(new Color(0,0,255));
				$c->setCustomColor(new Color(0,0,255));
				$d->setCustomColor(new Color(0,0,255));
			break;
			
			case 'yellow':
				$a->setCustomColor(new Color(255,255,0));
				$b->setCustomColor(new Color(255,255,0));
				$c->setCustomColor(new Color(255,255,0));
				$d->setCustomColor(new Color(255,255,0));			
			break;
			
			case 'green':
				$a->setCustomColor(new Color(0,255,0));
				$b->setCustomColor(new Color(0,255,0));
				$c->setCustomColor(new Color(0,255,0));
				$d->setCustomColor(new Color(0,255,0));			
			break;
		}
		
		$player->getArmorInventory()->setHelmet($a);
		$player->getArmorInventory()->setChestplate($b);
		$player->getArmorInventory()->setLeggings($c);
		$player->getArmorInventory()->setBoots($d);	
		
		$player->getArmorInventory()->sendContents($player);
	}
	
	private function giveKit(Player $player, $kit)
	{
		$player->getInventory()->clearAll();
		switch($kit)
		{
			case 'miner':
				$player->getInventory()->setItem(0, Item::get(Item::IRON_PICKAXE, 0, 1));
			break;
			
			case 'fighter':
				$player->getInventory()->setItem(0, Item::get(Item::STONE_SWORD, 0, 1));
			break;
			
			case 'marksman':
				$player->getInventory()->setItem(0, Item::get(Item::BOW, 0, 1));
                $player->getInventory()->setItem(1, Item::get(Item::ARROW, 0, 14));
			break;
			
			case 'chemist':
				$player->getInventory()->setItem(0, Item::get(283, 0, 1));
				$player->getInventory()->setItem(1, Item::get(438, 25, 5));
                $player->getInventory()->setItem(2, Item::get(438, 21, 5));
				
				$player->getArmorInventory()->setChestplate(Item::get(307));
				$player->getArmorInventory()->setLeggings(Item::get(304));
				$player->getArmorInventory()->setBoots(Item::get(309));
				$player->getArmorInventory()->sendContents($player);
			break;
			
			case 'bomber':
				$player->getInventory()->setItem(0, Item::get(259, 0, 1));
				$player->getInventory()->setItem(1, Item::get(46, 0, 5));
                $player->getInventory()->setItem(2, Item::get(283, 0, 1));
				
				$player->getArmorInventory()->setChestplate(Item::get(307));
				$player->getArmorInventory()->setLeggings(Item::get(304));
				$player->getArmorInventory()->setBoots(Item::get(309));
				$player->getArmorInventory()->sendContents($player);
			break;
		}
	}
	
	public function onInteract(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);
		if ($event->getItem()->getId() == 339){
			
			switch( $event->getItem()->getDamage() )
			{
				case 69:
					$this->sendClasses($player);
				break;
				case 666:
					$this->leaveArena($player);
				break;
			}
			return true;
		}
		
		if($tile instanceof Sign) 
		{
			if($this->mode == 101 )
			{
				$tile->setText(TextFormat::AQUA . "[Join]",TextFormat::YELLOW  . "0 / 12", "§f".$this->currentLevel, $this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . " Arena Registered!");
			} else {
				$text = $tile->getText();
				if($text[3] == $this->prefix)
				{
					if($text[0] == TextFormat::AQUA . "[Join]")
					{
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
						$namemap = str_replace("§f", "", $text[2]);
						
						$level = $this->getServer()->getLevelByName($namemap);
						$thespawn = $config->get($namemap . "_lobby");
						
						$spawn = new Position($thespawn[0] + 0.5 , $thespawn[1], $thespawn[2] + 0.5, $level);
						$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
						
						$player->teleport($spawn, 0, 0);
						$player->setHealth(20);
						$player->setGameMode(2);
						array_push($this->arenaplayers[$namemap], $player);
						return true;
					} else {
						$player->sendMessage($this->prefix . " You can't join");
					}
				}
			}
		}

		if($this->mode >= 1 && $this->mode <= 6)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			switch($this->mode)
			{
				case 1:
					$config->set($this->currentLevel . "_red_center_spawn", array( $block->getX() , $block->getY() + 1, $block->getZ() ));
					$player->sendMessage($this->prefix . " Red Spawn registered!, now tap for blue's spawn");
					$this->mode++;
				break;
				case 2:
					$config->set($this->currentLevel . "_blue_center_spawn", array( $block->getX() , $block->getY() + 1, $block->getZ() ));
					$player->sendMessage($this->prefix . " Blue Spawn registered!, now tap for green's spawn");
					$this->mode++;
				break;
				case 3:
					$config->set($this->currentLevel . "_green_center_spawn", array( $block->getX() , $block->getY() + 1, $block->getZ() ));
					$player->sendMessage($this->prefix . " Green Spawn registered!, now tap for yellow's spawn");
					$this->mode++;
				break;
				case 4:
					$config->set($this->currentLevel . "_yellow_center_spawn", array( $block->getX() , $block->getY() + 1, $block->getZ() ));
					$player->sendMessage($this->prefix . " Yellow Spawn registered!, now tap for the arena's lobby.");
					$this->mode++;
				break;
				case 5:
					$config->set($this->currentLevel . "_lobby", array( $block->getX() ,$block->getY() + 1, $block->getZ() ));
					$player->sendMessage($this->prefix . " Lobby has been registered!, tap anywhere to save!");
					$this->mode++;
				break;
				case 6:
					$level = $this->getServer()->getLevelByName( $this->currentLevel );
					$level->setSpawn = (new Vector3( $block->getX(),$block->getY() + 2, $block->getZ() ));
					
					$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
					$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
					$player->teleport($spawn, 0, 0);

					$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
					$config->set("arenas", $this->arenas);
					$config->save();

					$player->sendMessage($this->prefix . " Touch a sign where players can use to join.");
					$this->mode = 101;
				break;
			}
	}
	
	function sendClasses(Player $player)
	{
		$form = $this->getServer()->getPluginManager()->getPlugin("FormAPI")->createSimpleForm(function (Player $player, array $data)
		{
            if (isset($data[0]))
			{
                $button = $data[0];
                switch ($button)
				{
					case 0: $this->giveKit($player, 'fighter');
					break;
					case 1: $this->giveKit($player, 'marksman');
					break;
					case 2: $this->giveKit($player, 'miner');
					break;
					case 3: 
						if($player->hasPermission('pcpmb.chemist')) {
							$this->giveKit($player, 'chemist');
						} else {
							$player->sendMessage('•§c You are not eligible for this Class');
						}
					break;
					case 4:
						if($player->hasPermission('pcpmb.bomber')) {
							$this->giveKit($player, 'bomber');
						} else {
							$player->sendMessage('•§c You are not eligible for this Class');
						}
					break;
				}
				return true;
            }
        });
		$form->setTitle(" §l§fMicro Battles - Classes");
	
		$form->addButton("§lFighter", 1, "https://cdn3.iconfinder.com/data/icons/minecraft-icons/128/Stone_Sword.png");
        $form->addButton("§lMarksman", 1, "https://cdn4.iconfinder.com/data/icons/medieval-4/500/medieval-ancient-antique_16-128.png");
		$form->addButton("§lMiner", 1, "https://cdn3.iconfinder.com/data/icons/minecraft-icons/128/Iron_Pickaxe.png");
		$form->addButton("§6§lChemist", 1, "https://cdn2.iconfinder.com/data/icons/brainy-icons-science/120/0920-lab-flask04-128.png");
		$form->addButton("§6§lBomber", 1, "https://cdn3.iconfinder.com/data/icons/minecraft-icons/128/3D_Creeper.png");
		$form->sendToPlayer($player);
	}
	
	function refreshArenas()
	{
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$config->set("arenas",$this->arenas);
		foreach($this->arenas as $arena)
		{
			$config->set($arena . "PlayTime", 780);
			$config->set($arena . "StartTime", 90);
		}
		$config->save();
	}

	function dropitem(PlayerDropItemEvent $event) : void
    {
        $player = $event->getPlayer();
		if(in_array($player->getLevel()->getFolderName(), $this->arenas))
		{
			if ($event->getItem()->getId() == 339 && $event->getItem()->getDamage() == 69 or $event->getItem()->getDamage() == 666){
				$event->setCancelled();
			}

			if($this->getStatus($level) <> 1)
			{
				$event->setCancelled();
			}
		}
    }
	
	function givePrize(Player $player)
	{
		$core = $this->getServer()->getPluginManager()->getPlugin('CoreX2');
		$xp = mt_rand(15, 21);
		$core->data->addVal($player, "exp", $xp);
		$crate = $this->getServer()->getPluginManager()->getPlugin("CoolCrates")->getSessionManager()->getSession($player);
		$crate->addCrateKey("common.crate", 2);
		
		$form = $this->getServer()->getPluginManager()->getPlugin("FormAPI")->createSimpleForm(function (Player $player, array $data)
		{
            if (isset($data[0]))
			{
                $button = $data[0];
                switch ($button)
				{
					case 0: 
						//$this->getServer()->dispatchCommand($player, "top");
						break;
					default: 
						return true;
				}
				return true;
            }
        });
		
		$form->setTitle(" §l§bMicro §fBattles : PCP");
		$rank = $core->data->getVal($player, "rank");
		$div = $core->data->getVal($player, "div");
		$resp = $core->data->getVal($player, "respect");
		
		$s = "";
		$s .= "§f Experience points: +§a".$xp."§r\n";
		$s .= "§f Bonus: +§e2§f common crate keys§r\n";
		$s .= "§f Current ELO: §b".$rank." ".$div." §f| RP: §7[§c".$resp."§7] §f•§r\n";
		$s .= "§r\n";
        $form->setContent($s);
		
        $form->addButton("§lCheck Rankings", 1, "https://cdn4.iconfinder.com/data/icons/we-re-the-best/512/best-badge-cup-gold-medal-game-win-winner-gamification-first-award-acknowledge-acknowledgement-prize-victory-reward-conquest-premium-rank-ranking-gold-hero-star-quality-challenge-trophy-praise-victory-success-128.png");
		$form->addButton("Confirm", 1, "https://cdn1.iconfinder.com/data/icons/materia-arrows-symbols-vol-8/24/018_317_door_exit_logout-128.png");
		$form->sendToPlayer($player);
		
	}
}

class RefreshSigns extends Task
{
	
    public $prefix = TextFormat::BOLD . TextFormat::DARK_GRAY . "[" . TextFormat::AQUA . "Micro" . TextFormat::GREEN . "Battles" . TextFormat::DARK_GRAY . "]" . TextFormat::RESET . TextFormat::GRAY;
	
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
  
	public function onRun($tick)
	{
		$level = $this->plugin->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Sign) {	
				$text = $t->getText();
				if($text[3]== $this->plugin->prefix)
				{
                    $namemap = str_replace("§f", "", $text[2]);
					$arenalevel = $this->plugin->getServer()->getLevelByName( $namemap );
                    $playercount = count($arenalevel->getPlayers());
					$ingame = TextFormat::AQUA . "[Join]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($namemap . "PlayTime") != 780)
					{
						$ingame = TextFormat::RED . "[Running]";
					}
					if( $playercount >= 12)
					{
						$ingame = TextFormat::GOLD . "[Full]";
					}
					$t->setText($ingame, TextFormat::YELLOW  . $playercount . " / 12", $text[2], $this->prefix);
				}
			}
		}
	}

}

class GameSender extends Task
{
    public $prefix = TextFormat::BOLD . TextFormat::DARK_GRAY . "[" . TextFormat::AQUA . "Micro" . TextFormat::GREEN . "Battles" . TextFormat::DARK_GRAY . "]" . TextFormat::RESET . TextFormat::GRAY;
    
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

    public function getResetmap() {
		return new Resetmap($this);
    }
  
	public function onRun($tick)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$arenas = $config->get("arenas");
		if(!empty($arenas))
		{
			foreach($arenas as $arena)
			{
				$time = $config->get($arena . "PlayTime");
				$mins = floor($time / 60 % 60);
				$secs = floor($time % 60);
				if($secs < 10)
				{
					$secs = "0".$secs;
				}
				$timeToStart = $config->get($arena . "StartTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);
				if($levelArena instanceof Level)
				{
					$playersArena = $this->plugin->arenaplayers[$arena]; //$levelArena->getPlayers();
					if(count($playersArena) == 0)
					{
						$config->set($arena . "PlayTime", 780);
						$config->set($arena . "StartTime", 90);
					} else {
						if(count($playersArena) >= 2)
						{
							//$this->plugin->arenastatus[$arena] = 2; //waiting
							if($timeToStart > 0)
							{
								$timeToStart--;

								foreach($playersArena as $pl)
								{
									$pl->sendPopup("§7< §6" . $timeToStart . " seconds to start §7>");
								}
								if($timeToStart == 89)
								{
									$levelArena->setTime(7000);
									$levelArena->stopTime();
								}
								if($timeToStart == 10)
								{
									$this->refillChests($levelArena);
								}

								$config->set($arena . "StartTime", $timeToStart);
							} else {
								
								$aop = count( $levelArena->getPlayers() );
								$teams = $this->plugin->team;
								$activeteams = array_count_values($teams);
								
								if($aop >= 1)
								{
									if(count($activeteams) == 1)
									{
										$team = $teams[ key($teams) ];
										$this->plugin->getServer()->broadcastMessage();
										foreach($levelArena->getPlayers() as $pl)
										{
											if(array_key_exists($pl->getUniqueId(), $teams))
											{
												$pl->removeAllEffects();
												$pl->setHealth(20);
												$this->plugin->leaveArena($pl);
												$this->plugin->api->addMoney($pl, mt_rand(320, 400));//bullshit
												$this->plugin->givePrize($pl);
											} else {
												$this->plugin->leaveArena($pl);
											}
										}
									} else {
										foreach($levelArena->getPlayers() as $noob)
										{
											$noob->sendTip("§lRemaining : ". $mins. ":". $secs);
											$noob->sendPopup("§l§cRED:" . $activeteams["red"] . "  §9BLUE:" . $activeteams["blue"] . "  §aGREEN:" . $activeteams["green"] . "  §eYELLOW:" . $activeteams["yellow"] );
										}
									}
								}

								$time--;
								
								switch($time)
								{
									case 779:
										$this->plugin->arenastatus[$arena] = 3; //restricted
										$this->plugin->assignTeam($arena);
										foreach($levelArena->getPlayers() as $pl)
										{
											$pl->sendMessage("§7--------------------------------");
											$pl->sendMessage("§e§cAttention: §6The game will start soon!");
											$pl->sendMessage("§e§fUsing the map: §a" . $arena);
											#$pl->sendMessage("§e•>§bYou will be assigned into a team...");
											$pl->sendMessage("§7--------------------------------");
										}
									break;

									case 765:
										$this->plugin->arenastatus[$arena] = 1; //playing
										foreach($levelArena->getPlayers() as $pl)
										{
											$pl->addTitle("§l§aGame Start","§l§fEliminate all opposing teams");
										}
									break;
									
									case 480:
										$this->refillChests($levelArena);
										foreach($playersArena as $pl)
										{
											$pl->sendMessage("§l§aAttention §r: §7Chests has been refilled...");
										}
									break;
									
									case 0:
										$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
										$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
										foreach($levelArena->getPlayers() as $pl)
										{
											$pl->addTitle("§lGame Over","§cGame draw in map: §a" . $arena);
											$pl->setHealth(20);
											$this->plugin->leaveArena($pl);
											$this->getResetmap()->reload($levelArena);
										}
										$time = 780;
									break;
								}
								$config->set($arena . "PlayTime", $time);
							}
						} else {
							if($timeToStart <= 0)
							{
								foreach($playersArena as $pl)
								{
									$team = $this->plugin->team[$pl->getUniqueId()];
									$this->plugin->getServer()->broadcastMessage("§l§a". $team. " team won in ". $arena);
									$pl->setHealth(20);
									$this->plugin->leaveArena($pl);
									$this->plugin->api->addMoney($pl, mt_rand(390, 408));//bullshit
									$this->plugin->givePrize($pl);
									$this->getResetmap()->reload($levelArena);
								}
								$config->set($arena . "PlayTime", 780);
								$config->set($arena . "StartTime", 90);
							} else {
								foreach($playersArena as $pl)
								{
									$pl->sendPopup("§l§cNeed more players");
								}
								$config->set($arena . "PlayTime", 780);
								$config->set($arena . "StartTime", 90);
							}
						}
					}
				}
			}
		}
		$config->save();
	}
	
	public function refillChests(Level $level)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$tiles = $level->getTiles();
		foreach($tiles as $t)
		{
			if($t instanceof Chest) 
			{
				$chest = $t;
				$chest->getInventory()->clearAll();
				if($chest->getInventory() instanceof ChestInventory)
				{
					for($i=0 ; $i <=26; $i++)
					{
						$rand = rand(1,3);
						if($rand==1)
						{
							$k = array_rand($config->get("chestitems"));
							$v = $config->get("chestitems")[$k];
							$chest->getInventory()->setItem($i, Item::get($v[0], $v[1], $v[2]) );
						}
					}					
				}
			}
		}
	}
}
