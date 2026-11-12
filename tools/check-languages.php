<?php

declare(strict_types=1);

use pocketmine\command\PluginCommand;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\lang\Translatable;
use pocketmine\utils\TextFormat;
use ZulfahmiFjr\SoulWell\manager\ConfirmMenu;
use ZulfahmiFjr\SoulWell\manager\PrizeMenu;
use ZulfahmiFjr\SoulWell\manager\Reward;
use ZulfahmiFjr\SoulWell\manager\RewardManager;
use ZulfahmiFjr\SoulWell\service\LanguageService;
use ZulfahmiFjr\SoulWell\service\MenuService;

final class CheckConsole extends ConsoleCommandSender{

    public array $messages = [];

    public function __construct(){
    }

    public function sendMessage(Translatable|string $message):void{
     $this->messages[] = $message;
    }
}

$eng = json_decode(file_get_contents($root."/resources/languages/eng.json"), true, 512, JSON_THROW_ON_ERROR);
$ind = json_decode(file_get_contents($root."/resources/languages/ind.json"), true, 512, JSON_THROW_ON_ERROR);
check(array_keys($eng["messages"]) === array_keys($ind["messages"]), "Language catalogs have different message keys");
foreach($eng["messages"] as $key => $text){
 preg_match_all('/§[a-z0-9]/u', $text, $left);
 preg_match_all('/§[a-z0-9]/u', $ind["messages"][$key], $right);
 check($left[0] === $right[0], "Color/format sequence changed in ".$key);
 preg_match_all('/\{[A-Z_]+\}/', $text, $left);
 preg_match_all('/\{[A-Z_]+\}/', $ind["messages"][$key], $right);
 sort($left[0]);
 sort($right[0]);
 check($left[0] === $right[0], "Language placeholders differ in ".$key);
}
[$pl, $p, $server, $logger] = fixture($defaults, $temp."/language-command");
$command = new PluginCommand("soulwell", $pl, $pl);
$keysBefore = $pl->souls->getAll();
$configBefore = $pl->getConfig()->getAll();
check($pl->lang($p) === "eng", "Existing/new players must initially keep English");
check($pl->onCommand($p, $command, "sw", ["LANG", "IND"]), "Case-insensitive Indonesian selection failed");
check($pl->lang($p) === "ind" && str_contains(end($p->messages), "Bahasa Indonesia"), "Language confirmation did not use the new language");
$reloaded = new LanguageService($temp."/language-command-languages.yml");
check($reloaded->get("SOULWELLTEST") === "ind" && $reloaded->get("other player") === "eng", "Language did not survive reload or leaked to another account");
$before = file_get_contents($temp."/language-command-languages.yml");
check(!$reloaded->set($p, "fr") && file_get_contents($temp."/language-command-languages.yml") === $before, "Invalid language modified persistent preferences");
foreach([["lang"], ["lang", "fr"], ["lang", "eng", "extra"]] as $args){
 check(!$pl->onCommand($p, $command, "sw", $args) && $pl->lang($p) === "ind", "Invalid language command changed the preference");
 check(str_contains(end($p->messages), "Silakan gunakan perintah"), "Language usage was not localized");
}
$pl->rolls["soulwelltest"] = new stdClass();
check(!$pl->onCommand($p, $command, "sw", ["lang", "eng"]) && $pl->lang($p) === "ind", "Language changed during an active roll");
unset($pl->rolls["soulwelltest"]);
$console = new CheckConsole();
check(!$pl->onCommand($console, $command, "sw", ["lang", "ind"]) && str_contains(end($console->messages), "in the game"), "Console language command must be rejected without changing server defaults");
$pl->onCommand($p, $command, "sw", []);
check(str_contains(end($p->messages), "Perintah SoulWell") && str_contains(end($p->messages), "/sw lang"), "Localized command help is incomplete");
$pl->onCommand($p, $command, "sw", ["info"]);
check(str_contains(end($p->messages), "Anda dapat menghubungi"), "Plugin info was not translated formally");
check($pl->souls->getAll() === $keysBefore && $pl->getConfig()->getAll() === $configBefore, "Language changes modified keys or the loot configuration");
$confirmInd = ConfirmMenu::create($pl, $p);
check(str_contains($confirmInd["title"], LanguageService::IND_MARKER) && str_contains($confirmInd["title"], "Halo, SoulWellTest!"), "Indonesian confirmation marker/greeting missing");
check($confirmInd["buttons"][2]["text"] === "§f5x Putar §6(50 Kunci Jiwa)" && $confirmInd["buttons"][3]["text"] === "§e§lDAFTAR HADIAH", "Indonesian confirmation labels or costs are incorrect");
$vanilla = (new ReflectionClass(\ZulfahmiFjr\SoulWell\Main::class))->newInstanceWithoutConstructor();
field($vanilla, \pocketmine\plugin\PluginBase::class, "config", $pl->getConfig());
$vanilla->souls = $pl->souls;
$vanilla->languages = $pl->languages;
$nativeForm = ConfirmMenu::create($vanilla, $p);
check(str_contains($nativeForm["content"], "Anda harus memiliki setidaknya 10 Kunci Jiwa") && str_contains($nativeForm["content"], "Kunci Jiwa Anda sekarang"), "Configured vanilla confirmation text was not translated");
check($nativeForm["title"] === "§e§lKonfirmasi Soul Well" && end($nativeForm["buttons"])["text"] === "§lBatalkan Pembukaan", "Vanilla confirmation title or cancel button was not translated");
$manager = new RewardManager($defaults);
foreach(Reward::COLORS as $rarity => $color){
 $list = PrizeMenu::listing($manager, $rarity, "ind");
 check(str_contains($list["title"], LanguageService::IND_MARKER) && str_contains($list["content"], "Peluang kelangkaan"), "Indonesian prize list routing or caption is incorrect");
 foreach($manager->getCatalog($rarity) as $entry){
  $detail = PrizeMenu::details($manager, $entry["index"], true, "ind");
  check(str_contains($detail["buttons"][3]["text"], PrizeMenu::percent($entry["chance"])), "Localization changed the actual prize chance");
  check($detail["buttons"][1]["image"]["data"] === $entry["icon"], "Localization changed the prize icon");
  $canonical = $entry["reward"]->getItem();
  $localized = $entry["reward"]->getItem("ind");
  check($localized->getTypeId() === $canonical->getTypeId() && $localized->getCount() === $canonical->getCount() && $localized->getEnchantments() === $canonical->getEnchantments(), "Localization changed the actual item or enchants");
  $localized->setCount(99)->setCustomName("changed");
  check($entry["reward"]->getItem("ind")->getCount() === $canonical->getCount() && $entry["reward"]->getItem()->equalsExact($canonical), "Localized item cache mutated canonical rewards");
 }
}
$first = $manager->getCatalog("legendary")[0];
$detailInd = PrizeMenu::details($manager, $first["index"], true, "ind");
check(str_contains($detailInd["buttons"][1]["text"], "TARING NAUTILUS") && str_contains($detailInd["buttons"][4]["text"], "Ketajaman V"), "Default reward name or enchant translation missing");
check(str_contains($detailInd["buttons"][4]["text"], "Pedang legendaris Nautic Craft"), "Default reward lore translation missing");
check(PrizeMenu::listing($manager, "legendary", "ind", false)["title"] === "§e§lDaftar Hadiah SoulWell§r§r", "Vanilla list title was not translated");
check(PrizeMenu::details($manager, $first["index"], false, "ind")["title"] === "§e§lRincian Hadiah SoulWell§r§r", "Vanilla detail title was not translated");
$menus = new MenuService($pl);
$menus->listing($p, "legendary");
end($p->shownForms)->handleResponse($p, 5);
$pl->getScheduler()->mainThreadHeartbeat(1);
check(str_contains(end($p->shownForms)->jsonSerialize()["buttons"][1]["text"], "TARING NAUTILUS"), "Prize navigation lost the player language");
$englishPlayer = new CheckPlayer("Bread");
$server->recipients = [$p, $englishPlayer];
$pl->rewardService->give($p, [$first["reward"]]);
check(str_contains(end($p->messages), "baru saja menemukan") && str_contains(end($p->messages), "Legendaris Taring Nautilus"), "Indonesian subscriber received an English broadcast");
check(str_contains(end($englishPlayer->messages), "just found") && str_contains(end($englishPlayer->messages), "Legendary Nautilus Fang"), "English subscriber received an Indonesian broadcast");
check($p->titles[0][0] === "§6§lLEGENDARIS!" && $p->titles[0][1] === "§r§fTaring Nautilus", "Celebration title/subtitle not localized");
check(LanguageService::literal("ind", "High Powerful Diamondfish") === "High Powerful Diamondfish", "Literal translation corrupted partial words");
check(LanguageService::literal("ind", "§6§lNautilus Fang§r") === "§6§lTaring Nautilus§r", "Literal translation lost custom item colors");
check(LanguageService::text("ind", "ui.greeting", ["{PLAYER}" => "Bread"]) === "§fHalo, Bread!", "Player name was translated as an item literal");
check($pl->onCommand($p, $command, "sw", ["lang", "eng"]) && $pl->lang($p) === "eng", "Switching back to English failed");
check(ConfirmMenu::create($pl, $p)["buttons"][0]["text"] === "§f1x Roll §6(10 Soul Keys)", "English UI was not restored");
foreach([1, 3, 5] as $count){
 [$rollMain, $rollPlayer, $rollServer, $rollLogger] = fixture($legendary, $temp."/localized-roll-".$count);
 $rollMain->languages->set($rollPlayer, "ind");
 $roll = rollFixture($rollMain, $rollPlayer, $count);
 $chest = $rollPlayer->window;
 for($tick = 0; $tick < 500 && isset($rollMain->rolls["soulwelltest"]); $tick++) $roll->onRun();
 check(count($rollPlayer->bag->getContents()) === $count && count($rollServer->messages) === $count && count($rollPlayer->titles) === 1, "Localized multi-roll changed settlement counts");
 foreach($chest->getContents() as $item) check($item->equalsExact(array_values($rollPlayer->bag->getContents())[0]), "Localized winning reel and delivered item differ");
 check($rollLogger->errors === [], "Localized roll raised an exception");
}
if(isset($argv[2])){
 $preview = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
 $pl->languages->set($p, "ind");
 $pl->souls->set("soulwelltest", 999380);
 $preview["ind"] = ["confirm" => ConfirmMenu::create($pl, $p), "list" => PrizeMenu::listing($manager, "legendary", "ind"), "detail" => $detailInd];
 file_put_contents($argv[2], json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}
echo "PASS language persistence and isolation, command validation, format/placeholder parity, localized navigation and prizes, bilingual broadcasts and 1x/3x/5x settlement\n";
