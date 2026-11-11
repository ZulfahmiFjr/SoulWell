<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\player\Player;
use ZulfahmiFjr\SoulWell\Main;

final class ConfirmMenu{

    public const TITLE = "§e§lSoul Well Confirm§r§r";

    public static function create(Main $pl, Player $p):array{
     $keys = (int) $pl->souls->get(strtolower($p->getName()), 0);
     $buttons = [["text" => "§f1x Roll §6(10 Soul Keys)"], ["text" => "§f3x Roll §6(30 Soul Keys)"], ["text" => "§f5x Roll §6(50 Soul Keys)"], ["text" => "§e§lPRIZE LIST", "image" => ["type" => "path", "data" => "textures/ui/soulwell/prize_gift"]]];
     if($pl->hasCustomUi()) return ["type" => "form", "title" => self::TITLE."§fHi, ".$p->getName()."!", "content" => "§6Soul Keys: ".$keys, "buttons" => $buttons];
     $message = "§f       _________________________\n           §6§lSoul Well by Hypixel\n§r§f       -------------------------\n";
     $data = $pl->getConfig()->get("message", []);
     foreach(is_array($data) ? $data : (is_string($data) ? [$data] : []) as $text){
      $message .= str_replace(["{KEY}", "{PLAYER}"], [(string) $keys, $p->getName()], (string) $text)."§r\n\n";
     }
     $buttons[] = ["text" => "§lCancel Opening"];
     return ["type" => "form", "title" => "§e§lSoul Well Confirm", "content" => $message, "buttons" => $buttons];
    }
}
