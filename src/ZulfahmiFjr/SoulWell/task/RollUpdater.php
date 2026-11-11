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
use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\InvMenu;
use ZulfahmiFjr\SoulWell\manager\NoteBlockSound;

class RollUpdater extends Task{

    private $pl;
    private $p;
    private $delay;
    private $wellMenu;
    private $note = 12;
    private $low = 0;
    private int $rolls;
    private array $columns = [];
    private array $predictions = [];
    private InvMenu $menu;
    private bool $sent = false;
    private bool $opened = false;
    private bool $started = false;
    private bool $settled = false;
    private int $waiting = 0;
    private int $ready = 0;

    public function __construct(Main $pl, Player $p, int $delay, int $rolls = 1){
     $this->pl = $pl;
     $this->p = $p;
     $this->delay = $delay;
     $this->rolls = $rolls;
     $this->columns = $rolls === 5 ? [0, 2, 4, 6, 8] : ($rolls === 3 ? [2, 4, 6] : [4]);
     $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
     $menu->setName("§l§fSoul Well (".$rolls."x Roll)");
     $menu->setListener(InvMenu::readonly());
     $menu->setInventoryCloseListener(function(Player $p):void{
      $this->closeInventory($p);
     });
     $this->menu = $menu;
     $wellMenu = $menu->getInventory();
     $this->wellMenu = $wellMenu;
    }

    public function closeInventory(Player $p):void{
     if($this->settled) return;
     if(!$this->started){
      $this->abort();
      return;
     }
     $this->settled = true;
     if((!is_null($this->getHandler())) && (!$this->getHandler()->isCancelled())){
      foreach($this->columns as $c){
       $item = $this->predictions[$c] ?? null;
       if($item === null || $item->isNull()){
        $item = $this->getReward();
       }
       if($item !== null && !$item->isNull() && $p->isOnline()){
        $this->addItemToPlayer($p, $item);
        $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r§f".$item->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell§r§f.");
       }
      }
      $this->finish();
     }
    }

    public function getReward():?Item{
     $p = $this->p;
     $items = $this->pl->wellItems;
     if(!is_array($items) || $items === []) return null;
     $reward = array_rand($items, 1);
     $reward = $items[$reward];
     if(!isset($reward["id"]) || !isset($reward["amount"])){
      return null;
     }
     $idString = $reward["id"];
     $item = StringToItemParser::getInstance()->parse($idString);
     if($item === null || (int) $reward["amount"] <= 0) return null;
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
     if($this->settled) return;
     if(!$this->p->isOnline()){$this->abort();return;}
     if(!$this->sent){
      $this->sent = true;
      $this->menu->send($this->p, null, function(bool $success):void{
       if($this->settled) return;
       if(!$success){$this->abort();return;}
       $this->opened = true;
       $this->pl->getLogger()->info("SoulWell container sent to ".$this->p->getName());
      });
      return;
     }
     if(!$this->opened){
      $this->waiting += 3;
      if($this->waiting >= 200) $this->abort();
      return;
     }
     if(!$this->started){
      if($this->p->getCurrentWindow() !== $this->wellMenu){$this->abort();return;}
      $this->ready += 3;
      if($this->ready < 20) return;
      $this->started = true;
      $this->pl->getLogger()->info("SoulWell rolling ".$this->rolls." column(s) for ".$this->p->getName());
     }
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
        $colors = DyeColor::getAll();
        $itemEndRod = VanillaBlocks::END_ROD()->asItem();
        while($i < 54){
         $col = $i % 9;
         $row = intdiv($i, 9);
         if(!in_array($col, $this->columns, true)){
          if($row === 3 && $col >= min($this->columns) - 1 && $col <= max($this->columns) + 1){
           $wellInventory->setItem($i, $itemEndRod);
          }else{
           $randomColor = $colors[array_rand($colors)];
           $wellInventory->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor($randomColor)->asItem());
          }
         }
         $i++;
        }
        if($this->note === 11 || $this->note === 12){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(5), [$p]);
         if($this->note === 11){
          $this->note = 2;
         }else if($this->note === 12){
          $this->note = 0;
         }
        }else if($this->note === 0){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(2), [$p]);
         $this->note = 11;
        }else if($this->note === 2){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(9), [$p]);
         $this->note = 12;
        }
        foreach($this->columns as $c){
         $item = $this->getReward();
         if($item === null){$this->abort();return;}
         if($item !== null){
          $wellInventory->setItem($c + 45, $wellInventory->getItem($c + 36));
          $wellInventory->setItem($c + 36, $wellInventory->getItem($c + 27));
          $wellInventory->setItem($c + 27, $wellInventory->getItem($c + 18));
          $wellInventory->setItem($c + 18, $wellInventory->getItem($c + 9));
          $wellInventory->setItem($c + 9, $wellInventory->getItem($c));
          $wellInventory->setItem($c, $item);
          if($delay === 3){
           $this->predictions[$c] = $item;
          }
         }
        }
       }
      }else{
       $this->low--;
      }
      if($delay === -1){
       foreach($this->columns as $c){
        $wellInventory->setItem($c, VanillaBlocks::AIR()->asItem());
        $wellInventory->setItem($c + 9, VanillaBlocks::AIR()->asItem());
        $wellInventory->setItem($c + 18, VanillaBlocks::AIR()->asItem());
        $wellInventory->setItem($c + 36, VanillaBlocks::AIR()->asItem());
        $wellInventory->setItem($c + 45, VanillaBlocks::AIR()->asItem());
       }
      }
      if($delay === -15){
       $this->settled = true;
       foreach($this->columns as $c){
        $item = $wellInventory->getItem($c + 27);
        if(!$item->isNull()){
         $this->addItemToPlayer($p, $item);
         $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r§f".$item->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell§r§f.");
        }
       }
       $p->getWorld()->addSound($p->getPosition(), new \pocketmine\world\sound\XpLevelUpSound(30), [$p]);
       $this->finish();
       $p->removeCurrentWindow();
      }
     }
    }

    private function addItemToPlayer(Player $p, Item $item):void{
     $p->getInventory()->addItem($item);
    }

    private function finish():void{
     unset($this->pl->rolls[strtolower($this->p->getName())]);
     $this->getHandler()?->cancel();
    }

    public function abort():void{
     if($this->settled) return;
     $this->settled = true;
     $name = strtolower($this->p->getName());
     $this->pl->souls->set($name, (int) $this->pl->souls->get($name, 0) + $this->rolls * 10);
     $this->pl->souls->save();
     $this->finish();
     $network = \ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\InvMenuHandler::getPlayerManager()->getNullable($this->p)?->network;
     $network?->dropPending();
     if($this->p->isOnline()){
      if($this->p->getCurrentWindow() === $this->wellMenu) $this->p->removeCurrentWindow();
      $this->p->sendMessage("§f§lSoulWell§r§f: §7§oSoulWell roll was cancelled, your Soul Keys have been returned§r§f!");
     }
     $this->pl->getLogger()->warning("SoulWell roll cancelled for ".$name.", refunded ".($this->rolls * 10)." Soul Keys");
    }

}
