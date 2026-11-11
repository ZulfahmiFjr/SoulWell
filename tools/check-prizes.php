<?php

declare(strict_types=1);

use ZulfahmiFjr\SoulWell\manager\MenuForm;
use ZulfahmiFjr\SoulWell\manager\PrizeMenu;
use ZulfahmiFjr\SoulWell\manager\Reward;
use ZulfahmiFjr\SoulWell\manager\RewardManager;
use ZulfahmiFjr\SoulWell\manager\ConfirmMenu;
use ZulfahmiFjr\SoulWell\service\MenuService;
use pocketmine\form\FormValidationException;

$manager = new RewardManager($defaults);
$catalog = $manager->getCatalog();
$sum = array_fill_keys(array_keys(Reward::COLORS), 0.0);
foreach($catalog as $index => $entry){
 check($entry["index"] === $index, "Catalog item index must stay stable");
 $sum[$entry["reward"]->getRarity()] += $entry["chance"];
 $detail = PrizeMenu::details($manager, $index);
 check(str_contains($detail["buttons"][3]["text"], PrizeMenu::percent($entry["chance"])), "Displayed chance differs from the roll table");
 check($detail["buttons"][1]["image"]["data"] === $entry["icon"], "Detail icon differs from the catalog item");
 check(str_contains($detail["content"], $entry["type"]), "Prize detail lost the actual item type");
}
foreach($sum as $rarity => $total) check(abs($total - $defaults["rarities"][$rarity]) < 0.000001, "Item chances do not sum to the tier rate");
foreach(Reward::COLORS as $rarity => $color){
 $list = PrizeMenu::listing($manager, $rarity);
 check(count($list["buttons"]) === 5 + count($manager->getCatalog($rarity)), "List buttons and filtered rewards disagree");
 check(str_contains($list["title"], "§0".$rarity), "Rarity routing marker is missing");
}
$legend = $manager->getCatalog("legendary");
check(abs($legend[0]["chance"] - 0.75) < 0.000001, "Default Legendary item must have a 0.75% chance");
$detail = PrizeMenu::details($manager, $legend[0]["index"]);
check(str_contains($detail["buttons"][4]["text"], "Sharpness V") && str_contains($detail["buttons"][4]["text"], "Fire Aspect II") && str_contains($detail["buttons"][4]["text"], "Unbreaking III"), "Nautilus Fang must show its real enchants");
check(count(PrizeMenu::details($manager, $legend[0]["index"], false)["buttons"]) === 1, "Vanilla detail fallback must have only a Back button");
$weighted = $defaults;
$weighted["items"][$legend[0]["index"]]["weight"] = 3;
$changed = (new RewardManager($weighted))->getCatalog("legendary");
check(abs($changed[0]["chance"] - 1.5) < 0.000001 && abs($changed[1]["chance"] - 0.5) < 0.000001, "Catalog percentages ignored edited reward weights");
check(PrizeMenu::percent(0.00001) === "<0.01%" && PrizeMenu::percent(0) === "0%", "Tiny positive chances must not display as impossible");
$ui = json_decode(file_get_contents($root."/resources/ClassicLargeChest/ui/soulwell_ui.json"), true, 512, JSON_THROW_ON_ERROR);
PrizeMenu::configureUi($ui, $manager);
foreach(Reward::COLORS as $rarity => $color){
 $grid = $ui["prize_grid_".$rarity];
 $entries = $manager->getCatalog($rarity);
 check($grid["type"] === "collection_panel" && $grid["collection_name"] === "form_buttons", "Prize controls need a native form collection parent");
 foreach($grid["controls"] as $i => $control){
  $card = array_values($control)[0];
  check($card["collection_index"] === $i + 5 && $card["offset"] === [($i % 2) * 138, intdiv($i, 2) * 38], "Grid card index or two-column position is incorrect");
 }
 check($grid["size"][1] === (int) ceil(count($entries) / 2) * 38, "Scrolling content does not fit the configured prizes");
}
[$pl, $p] = fixture($defaults, $temp."/prize-navigation");
$menus = new MenuService($pl);
$keysBefore = $pl->souls->getAll();
$menus->confirmation($p);
$confirmation = end($p->shownForms);
check(count($confirmation->jsonSerialize()["buttons"]) === 4, "Confirmation must include Prize List after all three rolls");
$confirmation->handleResponse($p, 3);
$pl->getScheduler()->mainThreadHeartbeat(1);
$listing = end($p->shownForms);
check(str_contains($listing->jsonSerialize()["title"], "§0legendary"), "Prize entry must open the reference Legendary tab");
$listing->handleResponse($p, 5);
$pl->getScheduler()->mainThreadHeartbeat(2);
$selected = end($p->shownForms);
check(str_contains($selected->jsonSerialize()["buttons"][1]["text"], "NAUTILUS FANG"), "First Legendary card must open Nautilus Fang");
$selected->handleResponse($p, 0);
$pl->getScheduler()->mainThreadHeartbeat(3);
check(str_contains(end($p->shownForms)->jsonSerialize()["title"], "§0legendary"), "Back from detail must retain the rarity");
end($p->shownForms)->handleResponse($p, 1);
$pl->getScheduler()->mainThreadHeartbeat(4);
check(str_contains(end($p->shownForms)->jsonSerialize()["title"], "§0rare"), "Rarity tab click opened the wrong category");
end($p->shownForms)->handleResponse($p, 4);
$pl->getScheduler()->mainThreadHeartbeat(5);
check(str_contains(end($p->shownForms)->jsonSerialize()["title"], ConfirmMenu::TITLE), "Back from list must return to confirmation");
check($pl->souls->getAll() === $keysBefore && $p->bag->getContents() === [] && $pl->requestedRolls === [], "Browsing prizes must never charge keys or award items");
foreach([0 => 1, 1 => 3, 2 => 5] as $choice => $expected){
 $menus->confirmation($p);
 $form = end($p->shownForms);
 $form->handleResponse($p, $choice);
 $form->handleResponse($p, $choice);
 check(end($pl->requestedRolls) === $expected, "Roll selection route changed");
}
check($pl->requestedRolls === [1, 3, 5], "Duplicate form response started a second roll");
foreach([-1, 1000, 1.5, "0", false, []] as $value){
 $form = new MenuForm(["buttons" => [["text" => "test"]]], function():void{throw new RuntimeException("Invalid form response reached a callback");});
 try{
  $form->handleResponse($p, $value);
  throw new RuntimeException("Invalid form response was accepted");
 }catch(FormValidationException $e){
 }
}
$menus->confirmation($p);
$form = end($p->shownForms);
$before = count($p->shownForms);
$form->handleResponse($p, 3);
$p->online = false;
$pl->getScheduler()->mainThreadHeartbeat(6);
check(count($p->shownForms) === $before, "Deferred navigation must stop when a player disconnects");
if(isset($argv[2])){
 [$previewMain, $previewPlayer] = fixture($defaults, $temp."/prize-preview");
 file_put_contents($argv[2], json_encode(["ui" => $ui, "confirm" => ConfirmMenu::create($previewMain, $previewPlayer), "list" => PrizeMenu::listing($manager, "legendary"), "detail" => PrizeMenu::details($manager, $legend[0]["index"])], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}
echo "PASS config-derived prize chances and details, two-column grids, rarity navigation, read-only browsing, roll routing and replay validation\n";
