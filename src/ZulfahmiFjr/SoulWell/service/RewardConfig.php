<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

use pocketmine\utils\Config;

final class RewardConfig{

    public static function upgrade(Config $config, array $defaults):bool{
     if((int) $config->get("config-version", 0) >= 2) return false;
     $data = $config->getAll();
     if(!array_key_exists("rarities", $data)) $data["rarities"] = $defaults["rarities"];
     $effects = $data["celebration"] ?? [];
     if(is_array($effects)) $data["celebration"] = array_replace($defaults["celebration"], $effects);
     $items = $data["items"] ?? [];
     if(!is_array($items) || !array_is_list($items)) throw new \InvalidArgumentException("items must be a list before the rarity upgrade");
     $rarities = [];
     foreach($defaults["items"] as $item){
      if(!isset($item["key"])) $rarities[$item["id"]] = $item["rarity"];
     }
     foreach($items as &$item){
      if(!is_array($item)) throw new \InvalidArgumentException("each existing reward must be a mapping");
      if(!array_key_exists("rarity", $item)) $item["rarity"] = is_string($item["id"] ?? null) ? ($rarities[$item["id"]] ?? "common") : "common";
      if(!array_key_exists("weight", $item)) $item["weight"] = 1;
     }
     unset($item);
     foreach($defaults["items"] as $item){
      if(!isset($item["key"])) continue;
      $found = false;
      foreach($items as $old){
       if(($old["key"] ?? null) === $item["key"] || (($old["id"] ?? null) === $item["id"] && ($old["name"] ?? null) === ($item["name"] ?? null))){$found = true;break;}
      }
      if(!$found) $items[] = $item;
     }
     $data["items"] = $items;
     $data["config-version"] = 2;
     new \ZulfahmiFjr\SoulWell\manager\RewardManager($data);
     $path = $config->getPath();
     $backup = $path.".before-rarity-".gmdate("Ymd-His")."-".bin2hex(random_bytes(3));
     if(!copy($path, $backup)) throw new \RuntimeException("Unable to back up SoulWell config before rarity upgrade");
     $config->setAll($data);
     $config->save();
     return true;
    }
}
