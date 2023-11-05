<?php

namespace SoulWell\ZulfahmiFjr\task;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\data\bedrock\DyeColorIdMap;
use pocketmine\data\bedrock\item\upgrade\ItemDataUpgrader;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Language;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\EnchantmentInstance;

use SoulWell\ZulfahmiFjr\Main;
use skymin\InventoryLib\InvLibManager;
use skymin\InventoryLib\LibInvType;
use skymin\InventoryLib\InvLibAction;
use SoulWell\ZulfahmiFjr\manager\NoteBlockSound;

class RollUpdater extends Task{

    private $pl;
    private $p;
    private $delay;
    private $wellMenu;
    private float $note = 12;
    private float $low = 0;
    private $prediction = null;

    public function __construct(Main $pl, Player $p, $delay){
     $this->pl = $pl;
     $this->p = $p;
     $this->delay = $delay;
     $playerPos = $p->getPosition();
     $wellMenu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
     $wellMenu->setName("Soul Well");
//     $wellMenu = InvLibManager::create(LibInvType::DOUBLE_CHEST(), new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), 'Soul Well');
     $wellMenu->setListener(InvMenu::readonly());
     $wellMenu->setInventoryCloseListener(\Closure::fromCallable([$this, 'closeInventory']));
     $wellMenu->send($p);
     $this->wellMenu = $wellMenu;
    }

    public function closeInventory(Player $p):void{
     if((!is_null($this->getHandler())) && (!$this->getHandler()->isCancelled())){
      if($this->prediction === null){
       $item = $this->getReward();
       if($item !== null){
        if($p->isOnline()){
         $this->addItemToPlayer($p, $item);
         $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r§f".$item->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell§r§f.");
        }
       }
      }else if(($item = $this->prediction) instanceof Item){
       if($p->isOnline()){
        $this->addItemToPlayer($p, $item);
        $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r§f".$item->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell§r§f.");
       }
      }
      $this->getHandler()->cancel();
     }
    }

    public function getReward():?Item{
     $p = $this->p;
     $items = $this->pl->wellItems;
     $reward = array_rand($items, 1);
     $reward = $items[$reward];
     if(!isset($reward["name"]) || !isset($reward["amount"])){
      $this->pl->souls->set(strtolower($p->getName()), $this->pl->souls->get(strtolower($p->getName())) + 10);
      $this->pl->souls->save();
      return null;
     }
//     $item = ItemFactory::getInstance()->get($reward["id"], $reward["meta"], $reward["amount"]);
     $item = StringToItemParser::getInstance()->parse($reward["name"])->setCount($reward["amount"]);
     if(isset($reward["name"])){
      $item->setCustomName($reward["name"]);
     }
     if(isset($reward["lore"])){
      $item->setLore([$reward["lore"]]);
     }
     if(isset($reward["enchantments"])){
      foreach($reward["enchantments"] as $enchantName => $enchantData){
       $level = $enchantData["level"];
       if(!is_null($enchant = StringToEnchantmentParser::getInstance()->parse($enchantName))){
        $item->addEnchantment(new EnchantmentInstance($enchant, $level));
       }
      }
     }
     return $item;
    }

    public function onRun():void{
     $delay = $this->delay;
     $p = $this->p;
     $wellInventory = $this->wellMenu;
     if(($p instanceof Player) && ($p->isOnline())){
      if($this->low <= 0){
       if($this->delay <= 13){
        if($this->delay > 0){
         $this->low += 13 / $this->delay;
        }else{
         $this->low = 0;
        }
       }
       $this->delay--;
       if($delay >= 0){
        $i = 0;
        while($i < 54){
         if($i !== 4 && $i !== 13 && $i !== 22 && $i !== 31 && $i !== 40 && $i !== 49 && $i !== 30 && $i !== 32){
          $wellInventory->getInventory()->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColorIdMap::getInstance()->fromId(mt_rand(0, 15)))->asItem());
         }
         $i++;
        }
        $wellInventory->getInventory()->setItem(30, VanillaBlocks::END_ROD()->asItem());
        $wellInventory->getInventory()->setItem(32, VanillaBlocks::END_ROD()->asItem());
        if($this->note === 11 || $this->note === 12){
//         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(5));
         if($this->note === 11){
          $this->note = 2;
         }else if($this->note === 12){
          $this->note = 0;
         }
        }else if($this->note === 0){
//         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(2));
         $this->note = 11;
        }else if($this->note === 2){
//         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(9));
         $this->note = 12;
        }
        $item = $this->getReward();
        if($item !== null){
         $wellInventory->getInventory()->setItem(49, $wellInventory->getInventory()->getItem(40));
         $wellInventory->getInventory()->setItem(40, $wellInventory->getInventory()->getItem(31));
         $wellInventory->getInventory()->setItem(31, $wellInventory->getInventory()->getItem(22));
         $wellInventory->getInventory()->setItem(22, $wellInventory->getInventory()->getItem(13));
         $wellInventory->getInventory()->setItem(13, $wellInventory->getInventory()->getItem(4));
         $wellInventory->getInventory()->setItem(4, $item);
         if($delay === 3){
          $this->prediction = $item;
         }
        }
       }
      }else{
       $this->low--;
      }
      if($delay === -1){
       $wellInventory->getInventory()->setItem(4, VanillaItems::AIR());
       $wellInventory->getInventory()->setItem(13, VanillaItems::AIR());
       $wellInventory->getInventory()->setItem(22, VanillaItems::AIR());
       $wellInventory->getInventory()->setItem(40, VanillaItems::AIR());
       $wellInventory->getInventory()->setItem(49, VanillaItems::AIR());
      }
      if($delay === -15){
       $item = $wellInventory->getInventory()->getItem(31);
       $this->addItemToPlayer($p, $item);
       $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r§f".$item->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell§r§f.");
       $this->getHandler()->cancel();
       $p->removeCurrentWindow();
      }
     }
    }

    private function addItemToPlayer(Player $p, Item $item):void{
     $p->getInventory()->addItem($item);
    }
    
}
