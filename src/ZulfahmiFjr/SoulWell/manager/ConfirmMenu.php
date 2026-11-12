<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\player\Player;
use ZulfahmiFjr\SoulWell\Main;
use ZulfahmiFjr\SoulWell\service\LanguageService;

final class ConfirmMenu{

    public const TITLE = "§e§lSoul Well Confirm§r§r";

    public static function create(Main $pl, Player $p):array{
     $keys = (int) $pl->souls->get(strtolower($p->getName()), 0);
     $lang = $pl->lang($p);
     $buttons = [];
     foreach([1, 3, 5] as $rolls) $buttons[] = ["text" => $pl->text($p, "ui.roll", ["{ROLLS}" => (string) $rolls, "{COST}" => (string) ($rolls * 10)])];
     $buttons[] = ["text" => $pl->text($p, "ui.prize_button"), "image" => ["type" => "path", "data" => "textures/ui/soulwell/prize_gift"]];
     if($pl->hasCustomUi()) return ["type" => "form", "title" => self::TITLE.LanguageService::marker($lang).$pl->text($p, "ui.greeting", ["{PLAYER}" => $p->getName()]), "content" => $pl->text($p, "ui.balance", ["{KEY}" => (string) $keys]), "buttons" => $buttons];
     $message = $pl->text($p, "ui.banner");
     $data = $pl->getConfig()->get($lang === "ind" && $pl->getConfig()->exists("message-ind") ? "message-ind" : "message", []);
     foreach(is_array($data) ? $data : (is_string($data) ? [$data] : []) as $text){
      $message .= strtr(LanguageService::literal($lang, (string) $text), ["{KEY}" => (string) $keys, "{PLAYER}" => $p->getName()])."§r\n\n";
     }
     $buttons[] = ["text" => $pl->text($p, "ui.cancel")];
     return ["type" => "form", "title" => $pl->text($p, "ui.confirm_title"), "content" => $message, "buttons" => $buttons];
    }
}
