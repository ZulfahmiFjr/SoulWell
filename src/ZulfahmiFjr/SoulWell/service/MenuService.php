<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use ZulfahmiFjr\SoulWell\Main;
use ZulfahmiFjr\SoulWell\manager\ConfirmMenu;
use ZulfahmiFjr\SoulWell\manager\MenuForm;
use ZulfahmiFjr\SoulWell\manager\PrizeMenu;
use ZulfahmiFjr\SoulWell\manager\Reward;

final class MenuService{

    public function __construct(private Main $pl){
    }

    public function confirmation(Player $p):void{
     if(!$p->isOnline() || isset($this->pl->rolls[strtolower($p->getName())])) return;
     $p->sendForm(new MenuForm(ConfirmMenu::create($this->pl, $p), function(Player $p, int $choice):void{
      if($choice === 3){$this->later($p, function() use ($p):void{$this->listing($p, "legendary");});return;}
      $rolls = match($choice){0 => 1, 1 => 3, 2 => 5, default => 0};
      if($rolls > 0) $this->pl->startRoll($p, $rolls);
     }));
    }

    public function listing(Player $p, string $rarity):void{
     if(!$p->isOnline() || $this->pl->rewards === null || !isset(Reward::COLORS[$rarity]) || isset($this->pl->rolls[strtolower($p->getName())])) return;
     $entries = $this->pl->rewards->getCatalog($rarity);
     $p->sendForm(new MenuForm(PrizeMenu::listing($this->pl->rewards, $rarity, $this->pl->lang($p), $this->pl->hasCustomUi()), function(Player $p, int $choice) use ($rarity, $entries):void{
      if($choice < 4 && array_keys(Reward::COLORS)[$choice] === $rarity) return;
      $this->later($p, function() use ($p, $choice, $rarity, $entries):void{
       if($choice < 4){$this->listing($p, array_keys(Reward::COLORS)[$choice]);return;}
       if($choice === 4){$this->confirmation($p);return;}
       if(isset($entries[$choice - 5])) $this->details($p, $entries[$choice - 5]["index"], $rarity);
      });
     }));
    }

    public function details(Player $p, int $index, string $rarity):void{
     if(!$p->isOnline() || $this->pl->rewards === null || isset($this->pl->rolls[strtolower($p->getName())])) return;
     $p->sendForm(new MenuForm(PrizeMenu::details($this->pl->rewards, $index, $this->pl->hasCustomUi(), $this->pl->lang($p)), function(Player $p, int $choice) use ($rarity):void{
      if($choice === 0) $this->later($p, function() use ($p, $rarity):void{$this->listing($p, $rarity);});
     }));
    }

    private function later(Player $p, \Closure $action):void{
     $this->pl->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($p, $action):void{
      if($p->isOnline() && $this->pl->isEnabled() && !isset($this->pl->rolls[strtolower($p->getName())])) $action();
     }), 1);
    }
}
