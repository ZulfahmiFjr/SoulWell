<?php

namespace SoulWell\ZulfahmiFjr\task;

use pocketmine\scheduler\Task;
use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

use SoulWell\ZulfahmiFjr\Main;
use InvMenu\muqsit\invmenu\InvMenu;
use SoulWell\ZulfahmiFjr\manager\NoteBlockSound;

class RollUpdater extends Task{

    private $note = 12;
    private $low = 0;
    private $prediction = null;

    public function __construct(Main $pl, Player $p, $delay){
     $this->pl = $pl;
     $this->p = $p;
     $this->delay = $delay;
     $wellMenu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
     $wellMenu->readonly();
     $wellMenu->setInventoryCloseListener([$this, 'closeInventory']);
     $wellMenu->send($p);
     $this->wellMenu = $wellMenu;
    }

    public function closeInventory(Player $p, $inventory){
     if((!is_null($this->getHandler())) && (!$this->getHandler()->isCancelled())){
      if($this->prediction === null){
       $item = $this->getReward();
       if($item !== null){
        if($p->isOnline()){
         $p->getInventory()->addItem($item);
         $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r§f".$item->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell§r§f.");
        }
       }
      }else if(($item = $this->prediction) instanceof Item){
       if($p->isOnline()){
        $p->getInventory()->addItem($item);
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
     if(!isset($reward["id"]) || !isset($reward["meta"]) || !isset($reward["amount"])){
      $this->pl->souls->set($p->getLowerCaseName(), $this->pl->souls->get($p->getLowerCaseName()) + 10);
      $this->pl->souls->save();
      $p->sendMessage("§f§lSoulWell§r§f: §7§oAn error occurred in the SoulWell system your Soul Keys will be returned soon§r§f, §7§oplease report admin§r§f!");
      return null;
     }
     $item = Item::get($reward["id"], $reward["meta"], $reward["amount"]);
     if(isset($reward["name"])){
      $item->setCustomName($reward["name"]);
     }
     if(isset($reward["lore"])){
      $item->setLore([$reward["lore"]]);
     }
     if(isset($reward["enchantments"])){
      foreach($reward["enchantments"] as $enchantName => $enchantData){
       $level = $enchantData["level"];
       if(!is_null($enchant = Enchantment::getEnchantmentByName($enchantName))){
        $item->addEnchantment(new EnchantmentInstance($enchant, $level));
       }
      }
     }
     return $item;
    }

    public function onRun($timer){
     $delay = $this->delay;
     $p = $this->p;
     $wellMenu = $this->wellMenu;
     $wellInventory = $wellMenu->getInventory();
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
          $this->setItem($i, Item::get(Item::STAINED_GLASS_PANE, mt_rand(0, 15)));
         }
         $i++;
        }
        $this->setItem(30, Item::get(Item::END_ROD));
        $this->setItem(32, Item::get(Item::END_ROD));
        if($this->note === 11 || $this->note === 12){
         $p->getLevel()->addSound(new NoteBlockSound($p->getPosition(), 5));
         if($this->note === 11){
          $this->note = 2;
         }else if($this->note === 12){
          $this->note = 0;
         }
        }else if($this->note === 0){
         $p->getLevel()->addSound(new NoteBlockSound($p->getPosition(), 2));
         $this->note = 11;
        }else if($this->note === 2){
         $p->getLevel()->addSound(new NoteBlockSound($p->getPosition(), 9));
         $this->note = 12;
        }
        $item = $this->getReward();
        if($item !== null){
         $this->setItem(49, $wellInventory->getItem(40));
         $this->setItem(40, $wellInventory->getItem(31));
         $this->setItem(31, $wellInventory->getItem(22));
         $this->setItem(22, $wellInventory->getItem(13));
         $this->setItem(13, $wellInventory->getItem(4));
         $this->setItem(4, $item);
         if($delay === 3){
          $this->prediction = $item;
         }
        }
       }
      }else{
       $this->low--;
      }
      if($delay === -1){
       $this->setItem(4, Item::get(Item::AIR));
       $this->setItem(13, Item::get(Item::AIR));
       $this->setItem(22, Item::get(Item::AIR));
       $this->setItem(40, Item::get(Item::AIR));
       $this->setItem(49, Item::get(Item::AIR));
      }
      if($delay === -15){
       $item = $wellInventory->getItem(31);
       if($p->isOnline()){
        $p->getInventory()->addItem($item);
        $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r§f".$item->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell§r§f.");
       }
       $this->getHandler()->cancel();
       $p->removeWindow($wellInventory);
      }
     }
    }

    public function setItem($index, Item $item){
     $wellMenu = $this->wellMenu;
     $wellMenu->getInventory()->setItem($index, $item);
    }

}
