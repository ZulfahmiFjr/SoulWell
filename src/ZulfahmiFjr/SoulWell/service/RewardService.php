<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

use pocketmine\player\Player;
use pocketmine\world\sound\TotemUseSound;
use pocketmine\world\sound\XpLevelUpSound;
use ZulfahmiFjr\SoulWell\Main;

final class RewardService{

    public function __construct(private Main $pl){
    }

    public function give(Player $p, array $rewards):void{
     $best = null;
     foreach($rewards as $reward){
      $item = $reward->getItem();
      foreach($p->getInventory()->addItem($item) as $leftover) $p->getWorld()->dropItem($p->getPosition(), $leftover);
      $this->pl->getLogger()->info($p->getName()." won ".$reward->getLabel()." ".$reward->getName()." x".$item->getCount());
      if($best === null || $reward->getRank() > $best->getRank()) $best = $reward;
     }
     if($best === null) return;
     try{
      foreach($rewards as $reward){
       $item = $reward->getItem();
       $p->sendMessage("§f§lSoulWell§r§f: §7§oYou get §r".$reward->getColor().$reward->getName()." §7§owith amount §r§f".$item->getCount()." §7§ofrom SoulWell §r".$reward->getColor()."[".$reward->getLabel()."]§r§f.");
       if($reward->getRank() >= 2 && $this->pl->getConfig()->getNested("celebration.broadcast", true)){
        $text = (string) $this->pl->getConfig()->getNested("celebration.message", "§f§lSoulWell§r§f: §r§f{PLAYER} §7§ojust found §r{COLOR}[{RARITY} {ITEM}§r{COLOR}] §7§oin SoulWell§r§f!");
        $this->pl->getServer()->broadcastMessage(strtr($text, ["{PLAYER}" => $p->getName(), "{ITEM}" => $reward->getName(), "{RARITY}" => $reward->getLabel(), "{COLOR}" => $reward->getColor(), "{AMOUNT}" => (string) $item->getCount()]));
       }
      }
      if($this->pl->getConfig()->getNested("celebration.sound", true)){
       $p->getWorld()->addSound($p->getPosition(), new XpLevelUpSound(30), [$p]);
       if($best->getRank() >= 2) $p->getWorld()->addSound($p->getPosition(), new TotemUseSound(), [$p]);
      }
      if($best->getRank() >= 2 && $this->pl->getConfig()->getNested("celebration.title", true)) $p->sendTitle($best->getColor()."§l".strtoupper($best->getRarity())."!", "§r§f".$best->getName(), 5, 60, 20);
     }catch(\Throwable $e){
      $this->pl->getLogger()->logException($e);
     }
    }
}
