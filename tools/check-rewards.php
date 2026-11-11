<?php

declare(strict_types=1);

use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\utils\Config;
use ZulfahmiFjr\SoulWell\manager\Reward;
use ZulfahmiFjr\SoulWell\manager\RewardManager;
use ZulfahmiFjr\SoulWell\service\RewardConfig;

$root = dirname(__DIR__);
require $argv[1] ?? dirname($root)."/altay/vendor/autoload.php";
spl_autoload_register(function(string $class) use ($root):void{
 $prefix = "ZulfahmiFjr\\SoulWell\\";
 if(str_starts_with($class, $prefix)) require $root."/src/ZulfahmiFjr/SoulWell/".str_replace("\\", "/", substr($class, strlen($prefix))).".php";
});

function check(bool $ok, string $message):void{
 if(!$ok) throw new RuntimeException($message);
}

function rejects(array $data, string $message):void{
 try{
  new RewardManager($data);
 }catch(InvalidArgumentException $e){
  return;
 }
 throw new RuntimeException($message);
}

$defaults = yaml_parse_file($root."/resources/config.yml");
$roll = 1;
$pick = 1;
$calls = 0;
$rng = function(int $min, int $max) use (&$roll, &$pick, &$calls):int{
 $value = $calls++ % 2 === 0 ? $roll : $pick;
 check($value >= $min && $value <= $max, "Scripted random ticket outside requested range");
 return $value;
};
$manager = new RewardManager($defaults, $rng);
check($manager->getCount() === 21, "Expected 21 default rewards");
$counts = array_fill_keys(array_keys(Reward::COLORS), 0);
for($roll = 1; $roll <= 10000; $roll++) $counts[$manager->draw()->getRarity()]++;
check($counts === ["common" => 6000, "rare" => 2500, "epic" => 1200, "legendary" => 300], "Exact rarity ticket allocation is wrong");
$decimal = $defaults;
$decimal["rarities"] = ["common" => 60.01, "rare" => 24.99, "epic" => 12, "legendary" => 3];
$manager = new RewardManager($decimal, $rng);
$counts = array_fill_keys(array_keys(Reward::COLORS), 0);
for($roll = 1; $roll <= 10000; $roll++) $counts[$manager->draw()->getRarity()]++;
check($counts === ["common" => 6001, "rare" => 2499, "epic" => 1200, "legendary" => 300], "Decimal rarity ticket allocation is wrong");
$weighted = $defaults;
$weighted["rarities"] = ["common" => 100, "rare" => 0, "epic" => 0, "legendary" => 0];
$weighted["items"] = [["id" => "bread", "rarity" => "common", "weight" => 1], ["id" => "arrow", "rarity" => "common", "weight" => 3]];
$roll = 1;
$manager = new RewardManager($weighted, $rng);
$names = [];
for($pick = 1; $pick <= 4; $pick++) $names[] = $manager->draw()->getName();
check($names === ["Bread", "Arrow", "Arrow", "Arrow"], "Relative item weights are wrong");
$weighted["rarities"] = ["common" => 0, "rare" => 100, "epic" => 0, "legendary" => 0];
$weighted["items"] = [["id" => "diamond", "rarity" => "rare"]];
$pick = 1;
$manager = new RewardManager($weighted, $rng);
check($manager->draw()->getRarity() === "rare", "Zero-chance tiers must be skipped");
$reward = $manager->draw();
$changed = $reward->getItem();
$changed->setCount(50)->setCustomName("modified");
check($reward->getItem()->getCount() === 1 && $reward->getName() === "Diamond", "Cached reward items must stay immutable");
foreach($defaults["items"] as $entry){
 $item = StringToItemParser::getInstance()->parse($entry["id"]);
 foreach($entry["enchantments"] ?? [] as $name => $data){
  $enchant = StringToEnchantmentParser::getInstance()->parse($name);
  check(AvailableEnchantmentRegistry::getInstance()->isAvailableForItem($enchant, $item), "Default enchantment does not apply to ".$entry["id"].": ".$name);
  check($data["level"] <= $enchant->getMaxLevel(), "Default enchantment exceeds its normal maximum");
 }
}
$bad = $defaults;
$bad["rarities"]["common"] = 59;
rejects($bad, "Invalid percentage total was accepted");
foreach([-1, 100.001, NAN, INF, "60"] as $value){
 $bad = $defaults;
 $bad["rarities"]["common"] = $value;
 rejects($bad, "Invalid rarity percentage was accepted");
}
$bad = $defaults;
$bad["items"] = array_values(array_filter($bad["items"], fn(array $entry):bool => $entry["rarity"] !== "legendary"));
rejects($bad, "A positive-chance empty tier was accepted");
foreach(["invalid", ["broadcast" => "false"], ["message" => ""]] as $value){
 $bad = $defaults;
 $bad["celebration"] = $value;
 rejects($bad, "Invalid celebration config was accepted");
}
foreach([["id", "unknown_item"], ["id", "air"], ["rarity", "mythic"], ["weight", 0], ["weight", 1.5], ["amount", 0], ["amount", 5000], ["lore", [false]], ["enchantments", ["unknown_enchant" => ["level" => 1]]], ["enchantments", ["sharpness" => ["level" => 0]]]] as [$key, $value]){
 $bad = $defaults;
 $bad["items"][0][$key] = $value;
 rejects($bad, "Invalid reward field was accepted: ".$key);
}
$temp = sys_get_temp_dir()."/soulwell-rarity-check-".bin2hex(random_bytes(6));
mkdir($temp, 0700);
try{
 $legacy = ["message" => ["custom greeting"], "classic-chest-rp" => false, "custom-option" => "keep", "items" => [["id" => "diamond_sword", "amount" => 1, "name" => "Legacy Sword", "enchantments" => ["protection" => ["level" => 3]], "lore" => "first\nsecond"], ["id" => "cooked_chicken", "amount" => 16]]];
 $config = new Config($temp."/config.yml", Config::YAML, $legacy);
 $before = file_get_contents($config->getPath());
 check(RewardConfig::upgrade($config, $defaults), "Legacy config was not migrated");
 $backups = glob($temp."/config.yml.before-rarity-*");
 check(count($backups) === 1 && file_get_contents($backups[0]) === $before, "Migration did not keep an exact config backup");
 $upgraded = $config->getAll();
 foreach($legacy["items"] as $i => $entry){
  foreach($entry as $key => $value) check($upgraded["items"][$i][$key] === $value, "Migration changed an original reward field");
 }
 check($upgraded["custom-option"] === "keep" && $upgraded["message"] === ["custom greeting"] && $upgraded["classic-chest-rp"] === false, "Migration overwrote custom settings");
 check(count($upgraded["items"]) === 15 && $upgraded["items"][0]["rarity"] === "epic", "Migration did not append exactly the 13 new rewards");
 check(!RewardConfig::upgrade($config, $defaults) && $config->getAll() === $upgraded, "Migration must be idempotent");
 $invalid = new Config($temp."/invalid.yml", Config::YAML, array_replace($legacy, ["rarities" => ["common" => 99, "rare" => 0, "epic" => 0, "legendary" => 0]]));
 $before = file_get_contents($invalid->getPath());
 try{
  RewardConfig::upgrade($invalid, $defaults);
  throw new RuntimeException("Invalid migration was accepted");
 }catch(InvalidArgumentException $e){
  check(file_get_contents($invalid->getPath()) === $before && glob($temp."/invalid.yml.before-rarity-*") === [], "Failed validation modified the config");
 }
 echo "PASS rarity boundaries, decimal percentages, weights, item/enchant compatibility, immutable loot and backed-up idempotent migration\n";
 require __DIR__."/check-rolls.php";
 require __DIR__."/check-prizes.php";
}finally{
 foreach(glob($temp."/*") as $file) unlink($file);
 rmdir($temp);
}
