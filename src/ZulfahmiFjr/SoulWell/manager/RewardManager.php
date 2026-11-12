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
    private array $catalog = [];
    private array $nativeConfig = [];

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
     $icons = json_decode(file_get_contents(dirname(__DIR__, 4)."/resources/item_icons.json"), true, 512, JSON_THROW_ON_ERROR);
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
      $infoLore = array_slice($lore, 0, -1);
      $infoEnchants = [];
      $enchants = $entry["enchantments"] ?? [];
      if(!is_array($enchants)) throw new \InvalidArgumentException("items[".$i."].enchantments must be a mapping");
      foreach($enchants as $name => $enchantData){
       $enchant = is_string($name) ? StringToEnchantmentParser::getInstance()->parse($name) : null;
       $level = is_array($enchantData) ? ($enchantData["level"] ?? null) : null;
       if($enchant === null || !is_int($level) || $level < 1 || $level > 255) throw new \InvalidArgumentException("items[".$i."] has an unsupported enchantment or invalid level");
       $item->addEnchantment(new EnchantmentInstance($enchant, $level));
       $infoEnchants[] = ucwords(str_replace("_", " ", $name))." ".match($level){1 => "I", 2 => "II", 3 => "III", 4 => "IV", 5 => "V", 6 => "VI", 7 => "VII", 8 => "VIII", 9 => "IX", 10 => "X", default => (string) $level};
      }
      $this->totals[$rarity] += $weight;
      if($this->totals[$rarity] > 1000000000) throw new \InvalidArgumentException("total reward weight is too large for ".$rarity);
      $reward = new Reward($item, $rarity);
      $this->pools[$rarity][] = ["limit" => $this->totals[$rarity], "reward" => $reward];
      $iconId = str_replace("minecraft:", "", $id);
      $icon = $entry["icon"] ?? ($icons[($item->hasEnchantments() ? "enchanted:" : "").$iconId] ?? $icons[$iconId] ?? "textures/ui/soulwell/prize_unknown");
      if(!is_string($icon) || !preg_match('~^textures/[a-zA-Z0-9_./-]+$~D', $icon) || str_contains($icon, "..")) throw new \InvalidArgumentException("items[".$i."].icon must be a local texture path");
      $this->catalog[$i] = ["index" => $i, "reward" => $reward, "weight" => $weight, "icon" => $icon, "type" => $item->getVanillaName(), "lore" => $infoLore, "enchantments" => $infoEnchants];
      $this->count++;
     }
     foreach($this->rates as $rarity => $rate){
      if($rate > 0 && $this->totals[$rarity] === 0) throw new \InvalidArgumentException("rarities.".$rarity." has a positive chance but no rewards");
     }
     foreach($this->catalog as &$entry){
      $rarity = $entry["reward"]->getRarity();
      $entry["rarityChance"] = $this->rates[$rarity] / 100;
      $entry["chance"] = $entry["rarityChance"] * $entry["weight"] / $this->totals[$rarity];
      $entry["poolWeight"] = $this->totals[$rarity];
     }
     unset($entry);
     $this->nativeConfig = [...array_values($this->rates), $this->count];
     foreach($this->catalog as $entry){
      $this->nativeConfig[] = $entry["reward"]->getRank();
      $this->nativeConfig[] = $entry["weight"];
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

    public function getNativeConfig():array{
     return $this->nativeConfig;
    }

    public function getCount():int{
     return $this->count;
    }

    public function getCatalog(?string $rarity = null):array{
     return $rarity === null ? $this->catalog : array_values(array_filter($this->catalog, fn(array $entry):bool => $entry["reward"]->getRarity() === $rarity));
    }

    public function getRarityChance(string $rarity):float{
     return ($this->rates[$rarity] ?? 0) / 100;
    }

    public function getSummary():string{
     return implode(", ", array_map(fn(string $rarity):string => ucfirst($rarity)." ".($this->rates[$rarity] / 100)."% (".count($this->pools[$rarity])." items)", array_keys($this->rates)));
    }
}
