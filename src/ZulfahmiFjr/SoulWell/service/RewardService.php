<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

use pocketmine\player\Player;
use pocketmine\world\sound\TotemUseSound;
use pocketmine\world\sound\XpLevelUpSound;
use ZulfahmiFjr\SoulWell\Main;
use pocketmine\Server;

final class RewardService{

    public function __construct(private Main $pl){
    }

    public function give(Player $p, array $rewards):void{
     $best = null;
     $lang = $this->pl->lang($p);
     foreach($rewards as $reward){
      $item = $reward->getItem($lang);
      foreach($p->getInventory()->addItem($item) as $leftover) $p->getWorld()->dropItem($p->getPosition(), $leftover);
      $this->pl->getLogger()->info($p->getName()." won ".$reward->getLabel()." ".$reward->getName()." x".$item->getCount());
      if($best === null || $reward->getRank() > $best->getRank()) $best = $reward;
     }
     if($best === null) return;
     try{
      foreach($rewards as $reward){
       $item = $reward->getItem($lang);
       $p->sendMessage($this->pl->text($p, "reward.received", ["{COLOR}" => $reward->getColor(), "{ITEM}" => $reward->getName($lang), "{AMOUNT}" => (string) $item->getCount(), "{RARITY}" => $reward->getLabel($lang)]));
       if($reward->getRank() >= 2 && $this->pl->getConfig()->getNested("celebration.broadcast", true)){
        $groups = [];
        foreach($this->pl->getServer()->getBroadcastChannelSubscribers(Server::BROADCAST_CHANNEL_USERS) as $t) $groups[$this->pl->lang($t)][] = $t;
        foreach($groups as $locale => $recipients){
         $text = (string) $this->pl->getConfig()->getNested("celebration.message", LanguageService::text("eng", "reward.broadcast"));
         if($locale === "ind") $text = (string) $this->pl->getConfig()->getNested("celebration.message-ind", LanguageService::literal($locale, $text));
         $this->pl->getServer()->broadcastMessage(strtr($text, ["{PLAYER}" => $p->getName(), "{ITEM}" => $reward->getName($locale), "{RARITY}" => $reward->getLabel($locale), "{COLOR}" => $reward->getColor(), "{AMOUNT}" => (string) $item->getCount()]), $recipients);
        }
       }
      }
      if($this->pl->getConfig()->getNested("celebration.sound", true)){
       $p->getWorld()->addSound($p->getPosition(), new XpLevelUpSound(30), [$p]);
       if($best->getRank() >= 2) $p->getWorld()->addSound($p->getPosition(), new TotemUseSound(), [$p]);
      }
      if($best->getRank() >= 2 && $this->pl->getConfig()->getNested("celebration.title", true)) $p->sendTitle($best->getColor()."§l".mb_strtoupper($best->getLabel($lang), "UTF-8")."!", "§r§f".$best->getName($lang), 5, 60, 20);
     }catch(\Throwable $e){
      $this->pl->getLogger()->logException($e);
     }
    }
}
