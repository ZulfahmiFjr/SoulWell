<?php

namespace ZulfahmiFjr\SoulWell\task;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\item\Item;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\ItemTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\EnchantmentInstance;

use ZulfahmiFjr\SoulWell\Main;
use muqsit\invmenu\InvMenu;
use ZulfahmiFjr\SoulWell\manager\NoteBlockSound;

class RollUpdater extends Task{

    private $pl;
    private $p;
    private $delay;
    private $wellMenu;
    private $note = 12;
    private $low = 0;
    private $prediction = null;

    public function __construct(Main $pl, Player $p, $delay){
     $this->pl = $pl;
     $this->p = $p;
     $this->delay = $delay;
     $playerPos = $p->getPosition();
     $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
     $menu->setName("§l§fSoul Well");
     $menu->setListener(InvMenu::readonly());
     $menu->setInventoryCloseListener(function(Player $p):void{
      $this->closeInventory($p);
     });
     $menu->send($p);
     $wellMenu = $menu->getInventory();
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
     if(!isset($reward["id"]) || !isset($reward["amount"])){
      $this->pl->souls->set(strtolower($p->getName()), $this->pl->souls->get(strtolower($p->getName())) + 10);
      $this->pl->souls->save();
      $p->sendMessage("§f§lSoulWell§r§f: §7§oAn error occurred in the SoulWell system your Soul Keys will be returned soon§r§f, §7§oplease report admin§r§f!");
      return null;
     }
     $idString = $reward["id"];
     $item = StringToItemParser::getInstance()->parse($idString);
     if(isset($reward["amount"])){
      $item->setCount($reward["amount"]);
     }
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
          $colors = DyeColor::getAll();
          $randomColor = $colors[array_rand($colors)];
          $item = VanillaBlocks::STAINED_GLASS_PANE()->setColor($randomColor)->asItem();
          $wellInventory->setItem($i, $item);
         }
         $i++;
        }
        $itemEndRod = VanillaBlocks::END_ROD()->asItem();
        $wellInventory->setItem(30, $itemEndRod);
        $wellInventory->setItem(32, $itemEndRod);
        if($this->note === 11 || $this->note === 12){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(5));
         if($this->note === 11){
          $this->note = 2;
         }else if($this->note === 12){
          $this->note = 0;
         }
        }else if($this->note === 0){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(2));
         $this->note = 11;
        }else if($this->note === 2){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(9));
         $this->note = 12;
        }
        $item = $this->getReward();
        if($item !== null){
         $wellInventory->setItem(49, $wellInventory->getItem(40));
         $wellInventory->setItem(40, $wellInventory->getItem(31));
         $wellInventory->setItem(31, $wellInventory->getItem(22));
         $wellInventory->setItem(22, $wellInventory->getItem(13));
         $wellInventory->setItem(13, $wellInventory->getItem(4));
         $wellInventory->setItem(4, $item);
         if($delay === 3){
          $this->prediction = $item;
         }
        }
       }
      }else{
       $this->low--;
      }
      if($delay === -1){
       $wellInventory->setItem(4, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem(13, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem(22, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem(40, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem(49, VanillaBlocks::AIR()->asItem());
      }
      if($delay === -15){
       $item = $wellInventory->getItem(31);
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
