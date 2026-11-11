<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\EnchantmentInstance;

final class RewardManager{

    private array $rates = [];
    private array $pools = [];
    private array $totals = [];
    private \Closure $random;
    private int $count = 0;

    public function __construct(array $data, ?\Closure $random = null){
     $this->random = $random ?? \Closure::fromCallable("random_int");
     $rates = $data["rarities"] ?? null;
     if(!is_array($rates) || count($rates) !== count(Reward::COLORS) || array_diff_key($rates, Reward::COLORS) !== []) throw new \InvalidArgumentException("rarities must define common, rare, epic and legendary percentages");
     foreach(Reward::COLORS as $rarity => $color){
      $rate = $rates[$rarity] ?? null;
      if((!is_int($rate) && !is_float($rate)) || !is_finite((float) $rate) || $rate < 0 || $rate > 100 || abs($rate * 100 - round($rate * 100)) > 0.000001) throw new \InvalidArgumentException("rarities.".$rarity." must be between 0 and 100, with at most two decimal places");
      $this->rates[$rarity] = (int) round($rate * 100);
      $this->pools[$rarity] = [];
      $this->totals[$rarity] = 0;
     }
     if(array_sum($this->rates) !== 10000) throw new \InvalidArgumentException("rarity percentages must total exactly 100");
     $effects = $data["celebration"] ?? [];
     if(!is_array($effects)) throw new \InvalidArgumentException("celebration must be a mapping");
     foreach(["broadcast", "title", "sound"] as $key){
      if(array_key_exists($key, $effects) && !is_bool($effects[$key])) throw new \InvalidArgumentException("celebration.".$key." must be true or false");
     }
     if(isset($effects["message"]) && (!is_string($effects["message"]) || trim($effects["message"]) === "")) throw new \InvalidArgumentException("celebration.message must contain text");
     $items = $data["items"] ?? null;
     if(!is_array($items) || !array_is_list($items) || $items === []) throw new \InvalidArgumentException("items must contain at least one reward");
     foreach($items as $i => $entry){
      if(!is_array($entry)) throw new \InvalidArgumentException("items[".$i."] must be a reward definition");
      $rarity = $entry["rarity"] ?? "common";
      if(!is_string($rarity) || !isset(Reward::COLORS[$rarity])) throw new \InvalidArgumentException("items[".$i."] has an unknown rarity");
      $weight = $entry["weight"] ?? 1;
      if(!is_int($weight) || $weight < 1 || $weight > 1000000) throw new \InvalidArgumentException("items[".$i."].weight must be an integer between 1 and 1000000");
      $id = $entry["id"] ?? null;
      $item = is_string($id) ? StringToItemParser::getInstance()->parse($id) : null;
      if($item === null || $item->isNull()) throw new \InvalidArgumentException("items[".$i."] has an unsupported item id");
      $amount = $entry["amount"] ?? 1;
      if(!is_int($amount) || $amount < 1 || $amount > 2304) throw new \InvalidArgumentException("items[".$i."].amount must be an integer between 1 and 2304");
      $item->setCount($amount);
      if(isset($entry["name"])){if(!is_string($entry["name"])) throw new \InvalidArgumentException("items[".$i."].name must be text");$item->setCustomName($entry["name"]);}
      $lore = $entry["lore"] ?? [];
      if(is_string($lore)) $lore = explode("\n", $lore);
      if(!is_array($lore) || count(array_filter($lore, "is_string")) !== count($lore)) throw new \InvalidArgumentException("items[".$i."].lore must be text or a list of text lines");
      $lore = array_values($lore);
      $lore[] = "§r".Reward::COLORS[$rarity]."§l".strtoupper($rarity)." REWARD";
      $item->setLore($lore);
      $enchants = $entry["enchantments"] ?? [];
      if(!is_array($enchants)) throw new \InvalidArgumentException("items[".$i."].enchantments must be a mapping");
      foreach($enchants as $name => $enchantData){
       $enchant = is_string($name) ? StringToEnchantmentParser::getInstance()->parse($name) : null;
       $level = is_array($enchantData) ? ($enchantData["level"] ?? null) : null;
       if($enchant === null || !is_int($level) || $level < 1 || $level > 255) throw new \InvalidArgumentException("items[".$i."] has an unsupported enchantment or invalid level");
       $item->addEnchantment(new EnchantmentInstance($enchant, $level));
      }
      $this->totals[$rarity] += $weight;
      if($this->totals[$rarity] > 1000000000) throw new \InvalidArgumentException("total reward weight is too large for ".$rarity);
      $this->pools[$rarity][] = ["limit" => $this->totals[$rarity], "reward" => new Reward($item, $rarity)];
      $this->count++;
     }
     foreach($this->rates as $rarity => $rate){
      if($rate > 0 && $this->totals[$rarity] === 0) throw new \InvalidArgumentException("rarities.".$rarity." has a positive chance but no rewards");
     }
    }

    public function draw():Reward{
     $roll = ($this->random)(1, 10000);
     foreach($this->rates as $rarity => $rate){
      $roll -= $rate;
      if($roll > 0) continue;
      $pick = ($this->random)(1, $this->totals[$rarity]);
      foreach($this->pools[$rarity] as $entry){
       if($pick <= $entry["limit"]) return $entry["reward"];
      }
     }
     throw new \LogicException("Unable to select a SoulWell reward");
    }

    public function getCount():int{
     return $this->count;
    }

    public function getSummary():string{
     return implode(", ", array_map(fn(string $rarity):string => ucfirst($rarity)." ".($this->rates[$rarity] / 100)."% (".count($this->pools[$rarity])." items)", array_keys($this->rates)));
    }
}
