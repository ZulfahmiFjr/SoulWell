<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\utils\TextFormat;
use ZulfahmiFjr\SoulWell\service\LanguageService;

final class PrizeMenu{

    public const LIST_TITLE = "§e§lSoulWell Prize List§r§r";
    public const DETAIL_TITLE = "§e§lSoulWell Prize Detail§r§r";

    public static function percent(float $value):string{
     if($value > 0 && $value < 0.01) return "<0.01%";
     return rtrim(rtrim(number_format($value, 2, ".", ""), "0"), ".")."%";
    }

    public static function listing(RewardManager $manager, string $rarity, string $lang = "eng", bool $custom = true):array{
     $buttons = [];
     foreach(Reward::COLORS as $key => $color) $buttons[] = ["text" => $color."§l".mb_strtoupper(LanguageService::text($lang, "rarity.".$key), "UTF-8")];
     $buttons[] = ["text" => LanguageService::text($lang, "ui.back")];
     foreach($manager->getCatalog($rarity) as $entry){
      $reward = $entry["reward"];
      $buttons[] = ["text" => "§f".mb_strimwidth($reward->getName($lang), 0, 20, "...", "UTF-8")."\n§7x".$reward->getItem()->getCount()."\n".$reward->getColor()."§l".mb_strtoupper(LanguageService::text($lang, "rarity.".$rarity), "UTF-8"), "image" => ["type" => "path", "data" => $entry["icon"]]];
     }
     $text = Reward::COLORS[$rarity].LanguageService::text($lang, "ui.rarity_chance", ["{RARITY}" => LanguageService::text($lang, "rarity.".$rarity), "{CHANCE}" => self::percent($manager->getRarityChance($rarity))]);
     return ["type" => "form", "title" => $custom ? LanguageService::text($lang, "ui.prize_form_title")."§0".$rarity : LanguageService::text($lang, "ui.prize_form_title"), "content" => $text, "buttons" => $buttons];
    }

    public static function details(RewardManager $manager, int $index, bool $custom = true, string $lang = "eng"):array{
     $entry = $manager->getCatalog()[$index] ?? throw new \InvalidArgumentException("Unknown SoulWell prize");
     $reward = $entry["reward"];
     $rarity = $reward->getRarity();
     $tier = self::percent($entry["rarityChance"]);
     $odds = LanguageService::text($lang, $entry["weight"] === 1 ? "ui.odds" : "ui.weight", ["{POOL}" => (string) $entry["poolWeight"], "{WEIGHT}" => (string) $entry["weight"], "{RARITY}" => $reward->getLabel($lang)]);
     $chance = LanguageService::text($lang, "ui.chance", ["{CHANCE}" => self::percent($entry["chance"])]);
     $sub = LanguageService::text($lang, "ui.detail_rarity_chance", ["{RARITY}" => $reward->getLabel($lang), "{CHANCE}" => $tier])."\n§r§7".$odds;
     $name = mb_strimwidth(strtoupper($reward->getName($lang)), 0, 23, "...", "UTF-8");
     $info = ($name !== strtoupper($reward->getName($lang)) ? "§f".$reward->getName($lang)."\n" : "")."§7".LanguageService::literal($lang, $entry["type"])."\n";
     foreach($entry["enchantments"] as $enchant) $info .= "§d".LanguageService::literal($lang, $enchant)."\n";
     if($entry["lore"] !== []) $info .= "\n§7§o".implode("\n§7§o", array_map(fn(string $line):string => LanguageService::literal($lang, TextFormat::clean($line)), $entry["lore"]));
     $buttons = [["text" => LanguageService::text($lang, "ui.back_list")]];
     if($custom){
      $buttons[] = ["text" => $reward->getColor()."§l".$name, "image" => ["type" => "path", "data" => $entry["icon"]]];
      $buttons[] = ["text" => $reward->getColor()."§l".mb_strtoupper(LanguageService::text($lang, "rarity.".$rarity), "UTF-8")." §r§7x".$reward->getItem()->getCount()];
      $buttons[] = ["text" => $chance];
      $buttons[] = ["text" => $info];
      $buttons[] = ["text" => $sub];
     }
     return ["type" => "form", "title" => $custom ? LanguageService::text($lang, "ui.detail_form_title")."§0".$rarity : LanguageService::text($lang, "ui.detail_form_title"), "content" => $reward->getColor().$reward->getName($lang)."\n".$chance."\n".$sub."\n\n".$info, "buttons" => $buttons];
    }

    public static function configureUi(array &$ui, RewardManager $manager):void{
     foreach(Reward::COLORS as $rarity => $color){
      $entries = $manager->getCatalog($rarity);
      $cards = [];
      foreach($entries as $i => $entry) $cards[] = ["item_".$i."@soulwell.prize_card" => ["collection_index" => $i + 5, "offset" => [($i % 2) * 138, intdiv($i, 2) * 38]]];
      if($cards === []) $cards[] = ["empty@soulwell.prize_empty" => []];
      $ui["prize_grid_".$rarity] = ["type" => "collection_panel", "size" => [276, max(38, (int) ceil(count($entries) / 2) * 38)], "collection_name" => "form_buttons", "anchor_from" => "top_left", "anchor_to" => "top_left", "controls" => $cards];
      $ui["prize_grid_".$rarity."_ind"] = $ui["prize_grid_".$rarity];
      if($entries === []) $ui["prize_grid_".$rarity."_ind"]["controls"] = [["empty@soulwell.prize_empty_ind" => []]];
     }
    }
}
