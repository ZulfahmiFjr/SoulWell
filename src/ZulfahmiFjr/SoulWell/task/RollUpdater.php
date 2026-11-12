<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\task;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;

use ZulfahmiFjr\SoulWell\Main;
use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\InvMenu;
use ZulfahmiFjr\SoulWell\manager\NoteBlockSound;
use ZulfahmiFjr\SoulWell\service\LanguageService;

class RollUpdater extends Task{

    private const DELAYS = [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 5, 5, 5, 7, 9, 12, 16, 22];

    private Main $pl;
    private Player $p;
    private $wellMenu;
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
    private int $stepIndex = 0;
    private int $stepCooldown = 0;
    private int $finishCooldown = 15;

    public function __construct(Main $pl, Player $p, int $delay = 0, int $rolls = 1){
     $this->pl = $pl;
     $this->p = $p;
     $this->rolls = $rolls;
     $this->columns = $rolls === 5 ? [0, 2, 4, 6, 8] : ($rolls === 3 ? [2, 4, 6] : [4]);
     $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
     $menu->setName($pl->text($p, "ui.inventory_title", ["{ROLLS}" => (string) $rolls]).LanguageService::marker($pl->lang($p)));
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
     $this->settle($p);
    }

    private function settle(Player $p):void{
     if($this->settled) return;
     if(!$p->isOnline() || count($this->predictions) !== $this->rolls){$this->abort();return;}
     $this->settled = true;
     try{
      $this->pl->rewardService->give($p, $this->predictions);
     }finally{
      $this->finish();
     }
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
      $this->waiting++;
      if($this->waiting >= 100) $this->abort();
      return;
     }
     if(!$this->started){
      if($this->p->getCurrentWindow() !== $this->wellMenu){$this->abort();return;}
      $this->ready++;
      if($this->ready < 10) return;
      foreach($this->columns as $c) $this->predictions[$c] = $this->pl->rewards->draw();
      $this->started = true;
      $this->pl->getLogger()->info("SoulWell rolling ".$this->rolls." column(s) for ".$this->p->getName());
     }
     $p = $this->p;
     $wellInventory = $this->wellMenu;
     if($this->stepIndex < count(self::DELAYS)){
      if($this->stepCooldown > 0){
       $this->stepCooldown--;
       return;
      }
      if(!$this->pl->hasCustomUi()){
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
      }
      $note = $this->stepIndex >= count(self::DELAYS) - 5 ? match(count(self::DELAYS) - 1 - $this->stepIndex){ 0 => 24, 1 => 20, 2 => 18, 3 => 16, default => 14 } : match($this->stepIndex % 4){ 0 => 5, 1 => 2, 2 => 9, default => 12 };
      $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound($note), [$p]);
      foreach($this->columns as $c){
       $reward = $this->stepIndex === count(self::DELAYS) - 4 ? $this->predictions[$c] : $this->pl->rewards->draw();
       $item = $reward->getItem($this->pl->lang($p));
       $wellInventory->setItem($c + 45, $wellInventory->getItem($c + 36));
       $wellInventory->setItem($c + 36, $wellInventory->getItem($c + 27));
       $wellInventory->setItem($c + 27, $wellInventory->getItem($c + 18));
       $wellInventory->setItem($c + 18, $wellInventory->getItem($c + 9));
       $wellInventory->setItem($c + 9, $wellInventory->getItem($c));
       $wellInventory->setItem($c, $item);
      }
      $this->stepCooldown = self::DELAYS[$this->stepIndex] - 1;
      $this->stepIndex++;
      return;
     }
     if($this->finishCooldown === 15){
      foreach($this->columns as $c){
       $wellInventory->setItem($c, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem($c + 9, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem($c + 18, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem($c + 36, VanillaBlocks::AIR()->asItem());
       $wellInventory->setItem($c + 45, VanillaBlocks::AIR()->asItem());
      }
     }
     if($this->finishCooldown > 0){
      $this->finishCooldown--;
      return;
     }
     $this->settle($p);
     $p->removeCurrentWindow();
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
      $this->p->sendMessage($this->pl->text($this->p, "roll.cancelled"));
     }
     $this->pl->getLogger()->warning("SoulWell roll cancelled for ".$name.", refunded ".($this->rolls * 10)." Soul Keys");
    }

}
