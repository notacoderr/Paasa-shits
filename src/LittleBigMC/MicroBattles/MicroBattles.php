<?php
namespace LittleBigMC\MicroBattles;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityShootBowEvent;
//use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\tile\Chest;
use pocketmine\inventory\ChestInventory;
use pocketmine\utils\Color;
use onebone\economyapi\EconomyAPI;
use LittleBigMC\MicroBattles\Resetmap;
use LittleBigMC\MicroBattles\RefreshArena;

class MicroBattles extends PluginBase implements Listener
{

        public $prefix = TextFormat::GRAY . "[" . TextFormat::AQUA . TextFormat::BOLD . "Micro" . TextFormat::GREEN . "Battles" . TextFormat::RESET . TextFormat::GRAY . "]";
	public $mode = 0;
	public $arenas = array();
	public $currentLevel = "";
        public $reds = [], $blues = [], $greens = [], $yellows = [], $iswaiting = [], $isprotected = [], $isrestricted = [];
	
	public function onEnable()
	{
	$this->getLogger()->info(TextFormat::AQUA . "Micro§aBattles");
        $this->getServer()->getPluginManager()->registerEvents($this ,$this);
	$this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        if(!empty($this->economy))
        {
         $this->api = EconomyAPI::getInstance();
        }
		
	@mkdir($this->getDataFolder());
	$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		
	if($config->get("arenas")!=null)
	{
	 $this->arenas = $config->get("arenas");
	}
        foreach($this->arenas as $lev)
	{
	 $this->getServer()->loadLevel($lev);
	}
		
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
			
	if($config->get("chestitems")==null)
	{
	 $config->set("chestitems",$items);
	}
		
	$config->save();
	$this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
	$this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 10);

}

public function getZip()
{
 Return new RefreshArena($this);
}
	
public function onJoin(PlayerJoinEvent $event)
{
 $player = $event->getPlayer();
 if(in_array($player->getLevel()->getFolderName(), $this->arenas))
 {
  $this->leaveArena($player);
 }
}

public function onQuit(PlayerQuitEvent $event)
{
 $player = $event->getPlayer();
 if(in_array($player->getLevel()->getFolderName(), $this->arenas))
 {
  $this->leaveArena($player);
 }
}

    public function onMove(PlayerMoveEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level, $this->arenas))
		{
			if (!array_key_exists($player->getName(), $this->iswaiting)) //if the player is not waiting
			{
				if(array_key_exists($player->getName(), $this->isrestricted))
				{
				 $to = clone $event->getFrom();
				 $to->yaw = $event->getTo()->yaw;
				 $to->pitch = $event->getTo()->pitch;
				 $event->setTo($to);
				}
			}
		}
	}

	public function onShoot(EntityShootBowEvent $event)
	{
		$player = $event->getEntity();
		$level = $player->getLevel()->getFolderName(); 
		if($player instanceof Player && in_array($level,$this->arenas))
		{
			if (array_key_exists($player->getName(), $this->iswaiting) || array_key_exists($player->getName(), $this->isprotected))
			{
			 $event->setCancelled();
			 return true;
			}
			$event->setCancelled(false);
		}
	}
	
	public function onBlockBreak(BlockBreakEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName(); 
		if(in_array($level,$this->arenas))
		{
			if (array_key_exists($player->getName(), $this->iswaiting) || array_key_exists($player->getName(), $this->isrestricted))
			{
			 $event->setCancelled();
			 return true;
			}
			$event->setCancelled(false);
		}
	}
	
	public function onBlockPlace(BlockPlaceEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
			if (array_key_exists($player->getName(), $this->iswaiting) || array_key_exists($player->getName(), $this->isrestricted)) 
			{
			 $event->setCancelled();
			 return true;
			}
			$event->setCancelled(false);			
		}
	}
	
	public function onDamage(EntityDamageEvent $event)
	{
		if($event instanceof EntityDamageByEntityEvent)
		{
			if($event->getEntity() instanceof Player && $event->getDamager() instanceof Player)
			{
				$level = $event->getEntity()->getLevel()->getFolderName();
				if(in_array($level, $this->arenas))
				{
					$a = $event->getEntity()->getName(); $b = $event->getDamager()->getName();
					if(array_key_exists($a, $this->iswaiting) || array_key_exists($a, $this->isprotected)) { $event->setCancelled(); return true; }
					if(array_key_exists($a, $this->reds) && array_key_exists($b, $this->reds)) {  }
					if(array_key_exists($a, $this->yellows) && array_key_exists($b, $this->yellows)) { $event->setCancelled(); return true; }
					if(array_key_exists($a, $this->blues) && array_key_exists($b, $this->blues)) { $event->setCancelled(); return true; }
					if(array_key_exists($a, $this->greens) && array_key_exists($b, $this->greens)) { $event->setCancelled(); return true; }

					if( $event->getDamage() >= $event->getEntity()->getHealth() )
					{
					 $event->setCancelled();
					 $jugador = $event->getEntity();
					 $asassin = $event->getDamager();
					 $this->leaveArena($jugador);
					 foreach($jugador->getLevel()->getPlayers() as $pl)
					 {
					  $pl->sendMessage("§l§f".$asassin->getDisplayName()." §c•==§f|§c=======> §f" . $jugador->getDisplayName());
					 }
					}	
				}
			}
		}
	}

	public function onCommand(CommandSender $player, Command $cmd, $label, array $args) : bool {
		//$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
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
											array_push($this->arenas,$args[1]);
											$this->currentLevel = $args[1];
											$this->mode = 1;
											$player->sendMessage($this->prefix . " •> " . "Touch to set player spawns");
											$player->setGamemode(1);
											$player->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
											$name = $args[1];
											$this->getZip()->zip($player, $name);
											return true;
										} else {
											$player->sendMessage($this->prefix . " •> ERROR missing world.");
											return true;
										}
									}
									else
									{
										$player->sendMessage($this->prefix . " •> " . "ERROR missing parameters.");
										return true;
									}
							} else {
								$player->sendMessage($this->prefix . " •> " . "Oh no! You are not OP.");
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
							$player->sendMessage($this->prefix . " •> " . "Invalid command.");
							return true;
						}
					} else {
						$player->sendMessage($this->prefix . " •> " . "/mb <make-leave> : Create Arena | Leave the game");
						$player->sendMessage($this->prefix . " •> " . "/rankmb <Rank> <Player> : Set Rank(Ranks: Warrior, Warrior+, Archer, Pyromancer)");
						$player->sendMessage($this->prefix . " •> " . "/mbstart : Start the game in 10 seconds");
					}
					return true;
	
				case "mbstart":
				if($player->isOp())
				{
					$player->sendMessage($this->prefix . " •> " . "§aStarting in 10 seconds...");
					$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
					$config->set("arenas",$this->arenas);
					foreach($this->arenas as $arena)
					{
						$config->set($arena . "PlayTime", 780);
						$config->set($arena . "StartTime", 10);
					}
					$config->save();
				}
				return true;
			}
		} 
	}
	
	public function removeprotection(string $arena)
	{
		foreach ($this->isprotected as $name => $area)
		{
			if(strtolower($area) == strtolower($arena))
			{
				unset($this->isprotected[$name]);
			}
		}
	}
	
	public function removerestrictriction(string $arena)
	{
		foreach ($this->isrestricted as $name => $area)
		{
			if(strtolower($area) == strtolower($arena))
			{
				unset($this->isrestricted[$name]);
			}
		}
	}

	public function leaveArena(Player $player, $arena = null) : void
	{
		$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
		$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
		$player->teleport($spawn , 0, 0);		
		$player->setGameMode(2);
		$player->setFood(20);
		$player->setHealth(20);
		
		if (array_key_exists($player->getName(), $this->isprotected)){
			unset($this->isprotected[$player->getName()]);
		}
		if (array_key_exists($player->getName(), $this->iswaiting)){
			unset($this->iswaiting[$player->getName()]);
		}
		if (array_key_exists($player->getName(), $this->isrestricted)){
			unset($this->isrestricted[$player->getName()]);
		}
		if (array_key_exists($player->getName(), $this->reds)){
			unset($this->reds[$player->getName()]);
		}
		if (array_key_exists($player->getName(), $this->yellows)){
			unset($this->yellows[$player->getName()]);
		}
		if (array_key_exists($player->getName(), $this->blues)){
			unset($this->blues[$player->getName()]);
		}
		if (array_key_exists($player->getName(), $this->greens)){
			unset($this->greens[$player->getName()]);
		}
		
		$this->cleanPlayer($player);
	}

	function onTeleport(EntityLevelChangeEvent $event)
	{
        if ($event->getEntity() instanceof Player) 
		{
			$player = $event->getEntity();
			$from = $event->getOrigin()->getFolderName();
			$to = $event->getTarget()->getFolderName();
			if(in_array($from, $this->arenas) && !in_array($to, $this->arenas))
			{
				$event->getEntity()->setGameMode(2);
				
				if (array_key_exists($player->getName(), $this->isprotected)){
					unset($this->isprotected[$player->getName()]);
				}
				if (array_key_exists($player->getName(), $this->isrestricted)){
					unset($this->isrestricted[$player->getName()]);
				}
				if (array_key_exists($player->getName(), $this->iswaiting)){
					unset($this->iswaiting[$player->getName()]);
				}
				if (array_key_exists($player->getName(), $this->reds)){
					unset($this->reds[$player->getName()]);
				}
				if (array_key_exists($player->getName(), $this->yellows)){
					unset($this->yellows[$player->getName()]);
				}
				if (array_key_exists($player->getName(), $this->blues)){
					unset($this->blues[$player->getName()]);
				}
				if (array_key_exists($player->getName(), $this->greens)){
					unset($this->greens[$player->getName()]);
				}
				
				$this->cleanPlayer($player);
				return true;
			}
        }
	}
	
	private function cleanPlayer(Player $player)
	{
		$player->getInventory()->clearAll();
		$i = Item::get(0);
		
		$player->getArmorInventory()->setHelmet($i);
		$player->getArmorInventory()->setChestplate($i);
		$player->getArmorInventory()->setLeggings($i);
		$player->getArmorInventory()->setBoots($i);	
		
		$player->getArmorInventory()->sendContents($player);
		$player->setNameTag( $this->getServer()->getPluginManager()->getPlugin('PureChat')->getNametag($player) );
	}
	
	public function assignTeam($arena)
	{
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$i = 0;
		foreach($this->iswaiting as $name => $ar)
		{
			if(strtolower($ar) === strtolower($arena))
			{
				$player = $this->getServer()->getPlayer($name);
				$level = $this->getServer()->getLevelByName($arena);
				switch($i)
				{
					case 0: $thespawn = $config->get($arena . "Spawn1"); $color = 'r'; break;
					case 1: $thespawn = $config->get($arena . "Spawn2"); $color = 'b'; break;
					case 2: $thespawn = $config->get($arena . "Spawn3"); $color = 'g'; break;
					case 3: $thespawn = $config->get($arena . "Spawn4"); $color = 'y'; break;
					case 4: $thespawn = $config->get($arena . "Spawn5"); $color = 'r'; break;
					case 5: $thespawn = $config->get($arena . "Spawn6"); $color = 'b'; break;
					case 6: $thespawn = $config->get($arena . "Spawn7"); $color = 'g'; break;
					case 7: $thespawn = $config->get($arena . "Spawn8"); $color = 'y'; break;
					case 8: $thespawn = $config->get($arena . "Spawn9"); $color = 'r'; break;
					case 9: $thespawn = $config->get($arena . "Spawn10"); $color = 'b'; break;
					case 10: $thespawn = $config->get($arena . "Spawn11"); $color = 'g'; break;
					case 11: $thespawn = $config->get($arena . "Spawn12"); $color = 'y'; break;
				}
				$spawn = new Position($thespawn[0]+0.5 , $thespawn[1] ,$thespawn[2]+0.5 ,$level);
				$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
				$player->teleport($spawn, 0, 0);
				$player->setHealth(20);
				$player->setGameMode(0);
				
				$this->sendColors($player, $color);
				$this->joinTeam($player, $i);
				
				$this->isrestricted[ $player->getName() ] = $arena;
				
				unset( $this->iswaiting [ $name ] );
				$i += 1;
			}
		}
	}
	
	private function joinTeam(Player $player, $i)
	{
		switch($i)
		{
			case 0: case 4: case 8:
				$this->reds[$player->getName()] = $player;
				$player->setNameTag("§l§c[RED]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§c[RED]");
				$player->sendMessage('§l•§c> /mb quit §7- to leave the arena');
				break;
							
			case 1: case 5: case 9:
				$this->blues[$player->getName()] = $player;
				$player->setNameTag("§l§9[BLUE]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§9[BLUE]");
				$player->sendMessage('§l•§c> /mb quit §7- to leave the arena');
				break;
							
			case 2: case 6: case 10:
				$this->greens[$player->getName()] = $player;
				$player->setNameTag("§l§a[GREEN]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§a[GREEN]");
				$player->sendMessage('§l•§c> /mb quit §7- to leave the arena');
				break;
							
			case 3: case 7: case 11:
				$this->yellows[$player->getName()] = $player;
				$player->setNameTag("§l§e[YELLOW]" . $player->getName());
				$player->addTitle("§lPCP:§fMicro §bBattles", "§l§fYou are assigned into §l§e[YELLOW]");		
				$player->sendMessage('§l•§c> /mb quit §7- to leave the arena');
				break;
							
			default:
				$player->sendMessage($this->prefix . " •> " . "You can't join");
		}
		$player->getInventory()->setItem(0, Item::get(339, 69, 1)->setCustomName('§l§fClass Picker'));
		$player->getInventory()->setItem(8, Item::get(339, 666, 1)->setCustomName('§l§fTap to leave'));
		
	}
	
	public function sendColors(Player $player, string $color)
	{
		$a = Item::get(Item::LEATHER_CAP);
		$b = Item::get(Item::LEATHER_TUNIC);
		$c = Item::get(Item::LEATHER_PANTS);
		$d = Item::get(Item::LEATHER_BOOTS);
		switch($color)
		{
			case 'r':
				$a->setCustomColor(new Color(255,0,0));
				$b->setCustomColor(new Color(255,0,0));
				$c->setCustomColor(new Color(255,0,0));
				$d->setCustomColor(new Color(255,0,0));
			break;
			
			case 'b':
				$a->setCustomColor(new Color(0,0,255));
				$b->setCustomColor(new Color(0,0,255));
				$c->setCustomColor(new Color(0,0,255));
				$d->setCustomColor(new Color(0,0,255));
			break;
			
			case 'y':
				$a->setCustomColor(new Color(255,255,0));
				$b->setCustomColor(new Color(255,255,0));
				$c->setCustomColor(new Color(255,255,0));
				$d->setCustomColor(new Color(255,255,0));			
			break;
			
			case 'g':
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
			if($this->mode == 26 )
			{
				$tile->setText(TextFormat::AQUA . "[Join]",TextFormat::YELLOW  . "0 / 12","§f".$this->currentLevel,$this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . " •> " . "Arena Registered!");
			}
			else
			{
				$text = $tile->getText();
				if($text[3] == $this->prefix)
				{
					if($text[0]==TextFormat::AQUA . "[Join]")
					{
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
						$namemap = str_replace("§f", "", $text[2]);
						$level = $this->getServer()->getLevelByName($namemap);
						$thespawn = $config->get($namemap . "Lobby");
						
						$spawn = new Position($thespawn[0]+0.5 , $thespawn[1] ,$thespawn[2]+0.5 ,$level);
						$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
						
						$player->teleport($spawn, 0, 0);
						$player->getInventory()->clearAll();
                        $player->removeAllEffects();
                        $player->setHealth(20);
						$player->setGameMode(2);
						
						$this->iswaiting[ $player->getName() ] = $namemap; //beta
						$this->isprotected[ $player->getName() ] = $namemap; //beta
						return true;
					} else {
						$player->sendMessage($this->prefix . " •> " . "You can't join");
					}
				}
			}
		}
		if($this->mode >= 1 && $this->mode <= 12 )
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+1,$block->getZ()));
			$player->sendMessage($this->prefix . " •> " . "Spawn " . $this->mode . " has been registered!");
			$this->mode++;
			if($this->mode == 13)
			{
				$player->sendMessage($this->prefix . " •> " . "Tap to set the lobby spawn");
			}
			$config->save();
			return true;
		}
		if($this->mode == 13)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Lobby", array($block->getX(),$block->getY()+1,$block->getZ()));
			$player->sendMessage($this->prefix . " •> " . "Lobby has been registered!");
			$this->mode++;
			if($this->mode == 14)
			{
				$player->sendMessage($this->prefix . " •> " . "Tap anywhere to continue");
			}
			$config->save();
			return true;
		}
		
		if($this->mode == 14)
		{
			$level = $this->getServer()->getLevelByName($this->currentLevel);
			$level->setSpawn = (new Vector3($block->getX(),$block->getY()+2,$block->getZ()));
			$player->sendMessage($this->prefix . " •> " . "Touch a sign to register Arena!");
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn,0,0);
			
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set("arenas", $this->arenas);
			$config->save();
			$this->mode=26;
			return true;
		}
	}
	
	public function sendClasses(Player $player)
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
					default: $player->getInventory()->clearAll();
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
	
	public function refreshArenas()
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

	public function dropitem(PlayerDropItemEvent $event)
    {
        $player = $event->getPlayer();
		if(in_array($player->getLevel()->getFolderName(), $this->arenas) || array_key_exists($player->getName(), $this->iswaiting))
		{
			if ($event->getItem()->getId() == 339 && $event->getItem()->getDamage() == 69 or $event->getItem()->getDamage() == 666){
				$event->setCancelled();
				return true;
			}
			
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			if($config->get($level . "PlayTime") > 765)
			{
				$event->setCancelled(true);
				return true;
			}
			$event->setCancelled(false);
		}
    }
	
	public function givePrize(Player $player)
	{
		$name = $player->getLowerCaseName();
		$levelapi = $this->getServer()->getPluginManager()->getPlugin('LevelAPI');
		$xp = mt_rand(15, 21);
		$levelapi->addVal($name, "exp", $xp);
		$crate = $this->getServer()->getPluginManager()->getPlugin("CoolCrates")->getSessionManager()->getSession($player);
		$crate->addCrateKey("common.crate", 2);
		
		$form = $this->getServer()->getPluginManager()->getPlugin("FormAPI")->createSimpleForm(function (Player $player, array $data)
		{
            if (isset($data[0]))
			{
                $button = $data[0];
                switch ($button)
				{
					case 0: $this->getServer()->dispatchCommand($player, "top");
						break;	
					default: 
						return true;
				}
				return true;
            }
        });
		
		$form->setTitle(" §l§bMicro §fBattles : PCP");
		$rank = $levelapi->getVal($name, "rank");
		$div = $levelapi->getVal($name, "div");
		$resp = $levelapi->getVal($name, "respect");
		
		$s = "";
		$s .= "§l§f• Experience points: +§a".$xp."§r\n";
		$s .= "§l§f• Bonus: +§e2§f common crate keys§r\n";
		$s .= "§l§f• Current ELO: §b".$rank." ".$div." §f| RP: §7[§c".$resp."§7] §f•§r\n";
		$s .= "§r\n";
        $form->setContent($s);
		
        $form->addButton("§lCheck Rankings", 1, "https://cdn4.iconfinder.com/data/icons/we-re-the-best/512/best-badge-cup-gold-medal-game-win-winner-gamification-first-award-acknowledge-acknowledgement-prize-victory-reward-conquest-premium-rank-ranking-gold-hero-star-quality-challenge-trophy-praise-victory-success-128.png");
		$form->addButton("Confirm", 1, "https://cdn1.iconfinder.com/data/icons/materia-arrows-symbols-vol-8/24/018_317_door_exit_logout-128.png");
		$form->sendToPlayer($player);
		
	}
}

class RefreshSigns extends PluginTask {
    public $prefix = TextFormat::GRAY . "[" . TextFormat::AQUA . TextFormat::BOLD . "Micro" . TextFormat::GREEN . "Battles" . TextFormat::RESET . TextFormat::GRAY . "]";
	
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
				if($text[3]==$this->prefix)
				{
                    $namemap = str_replace("§f", "", $text[2]);
					$arenalevel = $this->plugin->getServer()->getLevelByName( $namemap );
                    $playercount = count($arenalevel->getPlayers());
					$ingame = TextFormat::AQUA . "[Join]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($namemap . "PlayTime") != 780)
					{
						$ingame = TextFormat::DARK_PURPLE . "[Running]";
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

class GameSender extends PluginTask
{
    public $prefix = TextFormat::GRAY . "[" . TextFormat::AQUA . TextFormat::BOLD . "Micro" . TextFormat::GREEN . "Battles" . TextFormat::RESET . TextFormat::GRAY . "]";
    
	public function __construct($plugin) {
		$this->plugin = $plugin;
		parent::__construct($plugin);
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
				$timeToStart = $config->get($arena . "StartTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);
				if($levelArena instanceof Level)
				{
					$playersArena = $levelArena->getPlayers();
					if( count($playersArena) == 0)
					{
						$config->set($arena . "PlayTime", 780);
						$config->set($arena . "StartTime", 90);
					}
					else
					{
						if(count($playersArena) >= 2 )
						{
							if($timeToStart>0)
							{
								$timeToStart--;
								foreach($playersArena as $pl)
								{
									$pl->sendPopup("§e< " . TextFormat::GREEN . $timeToStart . " seconds to start§e >");
								}
									if($timeToStart==89)
									{
										$levelArena->setTime(7000);
										$levelArena->stopTime();
									}
								if($timeToStart<=0)
								{
									$this->refillChests($levelArena);
								}
								$config->set($arena . "StartTime", $timeToStart);
							}
							else
							{
								$aop = count($levelArena->getPlayers());
								$colors = array();
								//$tages = array();
								//$colors = array();
								$reds = $this->plugin->reds;
								$blues = $this->plugin->blues; 
								$yellows = $this->plugin->yellows;
								$greens = $this->plugin->greens;

								if($aop>=1)
								{
									if (count($reds) >= 1 && count($yellows) == 0 && count($greens) == 0 && count($blues) == 0)  //Red member
									{
										foreach($this->plugin->getServer()->getOnlinePlayers() as $plpl)
										{
											$plpl->sendMessage($this->prefix . " •> " . "§l§c[RED] §l§bTeam is the Winner in the map §a" . $arena);
										}
										foreach($reds as $name => $pl)
										{
											$pl->removeAllEffects();
											$pl->setHealth(20);
											$this->plugin->leaveArena($pl);
											$this->plugin->api->addMoney($pl, mt_rand(320, 400));//bullshit
											$this->plugin->givePrize($pl);
										}
									}
								}
								if( ($aop>=2) )
								{
									foreach($playersArena as $pl)
									{
										$nametag = $pl->getNameTag();
										array_push($colors, $nametag);
									}
									$names = implode("-", $colors);
									$reds = substr_count($names, "§l§c[RED]");
									$blues = substr_count($names, "§l§9[BLUE]");
									$greens = substr_count($names, "§l§a[GREEN]");
									$yellows = substr_count($names, "§l§e[YELLOW]");
									foreach($playersArena as $pla)
									{
										$pla->sendPopup("§l§cRED:" . $reds . "  §9BLUE:" . $blues . "  §aGREEN:" . $greens . "  §eYELLOW:" . $yellows );
									}
								}
								
								$time--;
								
								switch($time)
								{
									case 779:
										$this->plugin->assignTeam($arena);
										foreach($playersArena as $pl)
										{
											$pl->sendMessage("§e•>--------------------------------");
											$pl->sendMessage("§e•>§cAttention: §6The game will start soon!");
											$pl->sendMessage("§e•>§fUsing the map: §a" . $arena);
											$pl->sendMessage("§e•>§binitiating §a15 §bseconds of mercy time");
											$pl->sendMessage("§e•>--------------------------------");
										}
									break;

									case 765:
										$this->plugin->removerestrictriction($arena);
										foreach($playersArena as $pl)
										{
											$pl->addTitle("§l§aGame Start","§l§fNo PVP for §a15 §fseconds, goodluck!");
										}
									break;
									
									case 750:
										$this->plugin->removeprotection($arena);
										foreach($playersArena as $pl)
										{
											$pl->addTitle("§l§cMe§7r§ccy Time", "§f§lHas been lifted, eliminate all enemies.");
										}
									break;
									
									case 480:
										$this->refillChests($levelArena);
										foreach($playersArena as $pl)
										{
											$pl->sendMessage("§lAttention §r•> §7Chests have been refilled...");
										}
									break;
									
									default:
									if($time >= 180)
									{
										$time2 = $time - 180;
										$minutes = $time2 / 60;
									} else {
										$minutes = $time / 60;
										if(is_int($minutes) && $minutes>0)
										{
											foreach($playersArena as $pl)
											{
												$pl->sendMessage($this->prefix . " •> " . $minutes . " minutes remaining");
											}
										}
										if($time == 30 || $time == 15 || $time == 10 || $time ==5 || $time ==4 || $time ==3 || $time ==2 || $time == 1)
										{
											foreach($playersArena as $pl)
											{
												$pl->sendMessage($this->prefix . " •> " . $time . " seconds remaining");
											}
										}
										if($time <= 0)
										{
											$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
											$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
											foreach($playersArena as $pl)
											{
												$pl->addTitle("§lGame Over","§cGame draw in map: §a" . $arena);
												$pl->setHealth(20);
												$this->plugin->leaveArena($pl);
												$this->getResetmap()->reload($levelArena);
											}
											$time = 780;
										}
									}
								}
								$config->set($arena . "PlayTime", $time);
							}
						} else {
							if($timeToStart<=0)
							{
								foreach($playersArena as $pl)
								{
									foreach($this->plugin->getServer()->getOnlinePlayers() as $plpl)
									{
										$plpl->sendMessage($this->prefix . " •> ".$pl->getNameTag() . "§l§b won in map : §a" . $arena);
									}
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
