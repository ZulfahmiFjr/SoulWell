<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\utils\TextFormat;

final class PrizeMenu{

    public const LIST_TITLE = "§e§lSoulWell Prize List§r§r";
    public const DETAIL_TITLE = "§e§lSoulWell Prize Detail§r§r";

    public static function percent(float $value):string{
     if($value > 0 && $value < 0.01) return "<0.01%";
     return rtrim(rtrim(number_format($value, 2, ".", ""), "0"), ".")."%";
    }

    public static function listing(RewardManager $manager, string $rarity):array{
     $buttons = [];
     foreach(Reward::COLORS as $key => $color) $buttons[] = ["text" => $color.($key === $rarity ? "§l" : "").strtoupper($key)];
     $buttons[] = ["text" => "§fBack"];
     foreach($manager->getCatalog($rarity) as $entry){
      $reward = $entry["reward"];
      $buttons[] = ["text" => "§f".mb_strimwidth($reward->getName(), 0, 20, "...", "UTF-8")."\n§7x".$reward->getItem()->getCount()."\n".$reward->getColor()."§l".strtoupper($rarity), "image" => ["type" => "path", "data" => $entry["icon"]]];
     }
     $text = Reward::COLORS[$rarity].ucfirst($rarity)." rarity chance: ".self::percent($manager->getRarityChance($rarity));
     return ["type" => "form", "title" => self::LIST_TITLE."§0".$rarity, "content" => $text, "buttons" => $buttons];
    }

    public static function details(RewardManager $manager, int $index, bool $custom = true):array{
     $entry = $manager->getCatalog()[$index] ?? throw new \InvalidArgumentException("Unknown SoulWell prize");
     $reward = $entry["reward"];
     $rarity = $reward->getRarity();
     $tier = self::percent($entry["rarityChance"]);
     $odds = $entry["weight"] === 1 ? "(1 in ".$entry["poolWeight"]." ".$reward->getLabel()." item chance)" : "(Item weight ".$entry["weight"]." of ".$entry["poolWeight"].")";
     $chance = "§eChance per roll: §l".self::percent($entry["chance"]);
     $sub = "§7".$reward->getLabel()." rarity chance: §e".$tier."\n§r§7".$odds;
     $name = mb_strimwidth(strtoupper($reward->getName()), 0, 23, "...", "UTF-8");
     $info = ($name !== strtoupper($reward->getName()) ? "§f".$reward->getName()."\n" : "")."§7".$entry["type"]."\n";
     foreach($entry["enchantments"] as $enchant) $info .= "§d".$enchant."\n";
     if($entry["lore"] !== []) $info .= "\n§7§o".implode("\n§7§o", array_map(fn(string $line):string => TextFormat::clean($line), $entry["lore"]));
     $buttons = [["text" => "§fBack to Prize List"]];
     if($custom){
      $buttons[] = ["text" => $reward->getColor()."§l".$name, "image" => ["type" => "path", "data" => $entry["icon"]]];
      $buttons[] = ["text" => $reward->getColor()."§l".strtoupper($rarity)." §r§7x".$reward->getItem()->getCount()];
      $buttons[] = ["text" => $chance];
      $buttons[] = ["text" => $info];
      $buttons[] = ["text" => $sub];
     }
     return ["type" => "form", "title" => self::DETAIL_TITLE."§0".$rarity, "content" => $reward->getColor().$reward->getName()."\n".$chance."\n".$sub."\n\n".$info, "buttons" => $buttons];
    }

    public static function configureUi(array &$ui, RewardManager $manager):void{
     foreach(Reward::COLORS as $rarity => $color){
      $entries = $manager->getCatalog($rarity);
      $cards = [];
      foreach($entries as $i => $entry) $cards[] = ["item_".$i."@soulwell.prize_card" => ["collection_index" => $i + 5, "offset" => [($i % 2) * 138, intdiv($i, 2) * 38]]];
      if($cards === []) $cards[] = ["empty@soulwell.prize_empty" => []];
      $ui["prize_grid_".$rarity] = ["type" => "collection_panel", "size" => [276, max(38, (int) ceil(count($entries) / 2) * 38)], "collection_name" => "form_buttons", "anchor_from" => "top_left", "anchor_to" => "top_left", "controls" => $cards];
     }
    }
}
