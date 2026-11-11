<?php

declare(strict_types=1);

use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLogger;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\SimpleInventory;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\world\sound\Sound;
use pocketmine\world\sound\TotemUseSound;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\lang\Translatable;
use pocketmine\utils\Config;
use ZulfahmiFjr\SoulWell\Main;
use ZulfahmiFjr\SoulWell\manager\Reward;
use ZulfahmiFjr\SoulWell\manager\RewardManager;
use ZulfahmiFjr\SoulWell\service\RewardService;
use ZulfahmiFjr\SoulWell\task\RollUpdater;
use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\InvMenuHandler;
use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\session\PlayerManager;

final class CheckLogger extends SimpleLogger{

    public array $errors = [];

    public function log($level, $message){
    }

    public function logException(Throwable $e, $trace = null){
     $this->errors[] = $e;
    }
}

final class CheckServer extends Server{

    public array $messages = [];

    public function __construct(){
    }

    public function broadcastMessage(Translatable|string $message, ?array $recipients = null):int{
     $this->messages[] = $message;
     return 1;
    }
}

final class CheckWorld extends World{

    public array $sounds = [];
    public array $drops = [];

    public function __construct(){
    }

    public function isLoaded():bool{
     return true;
    }

    public function addSound(Vector3 $pos, Sound $sound, ?array $players = null):void{
     check(count($sound->encode($pos)) > 0, "Sound must encode for the installed Altay protocol");
     check($players !== null && count($players) === 1, "Celebration audio must target the winner only");
     $this->sounds[] = $sound;
    }

    public function dropItem(Vector3 $source, Item $item, ?Vector3 $motion = null, int $delay = 10):?ItemEntity{
     $this->drops[] = clone $item;
     return null;
    }
}

final class CheckPlayer extends Player{

    public PlayerInventory $bag;
    public CheckWorld $testWorld;
    public ?Inventory $window = null;
    public bool $online = true;
    public bool $failTitle = false;
    public array $messages = [];
    public array $titles = [];

    public function __construct(){
     $this->bag = new PlayerInventory($this);
     $this->testWorld = new CheckWorld();
    }

    public function __destruct(){
    }

    public function isOnline():bool{
     return $this->online;
    }

    public function getName():string{
     return "SoulWellTest";
    }

    public function getId():int{
     return spl_object_id($this);
    }

    public function getInventory():PlayerInventory{
     return $this->bag;
    }

    public function getWorld():World{
     return $this->testWorld;
    }

    public function getPosition():Position{
     return new Position(0, 64, 0, $this->testWorld);
    }

    public function getCurrentWindow():?Inventory{
     return $this->window;
    }

    public function removeCurrentWindow():void{
     $this->window = null;
    }

    public function sendMessage(Translatable|string $message):void{
     $this->messages[] = $message;
    }

    public function sendTitle(string $title, string $subtitle = "", int $fadeIn = -1, int $stay = -1, int $fadeOut = -1):void{
     if($this->failTitle) throw new RuntimeException("Simulated title transport failure");
     $this->titles[] = [$title, $subtitle];
    }
}

final class CheckMain extends Main{

    public function hasCustomUi():bool{
     return true;
    }
}

function field(object $target, string $class, string $name, mixed $value):void{
 (new ReflectionProperty($class, $name))->setValue($target, $value);
}

function fixture(array $data, string $path):array{
 $pl = (new ReflectionClass(CheckMain::class))->newInstanceWithoutConstructor();
 $server = new CheckServer();
 $logger = new CheckLogger();
 $config = new Config($path, Config::YAML);
 $config->setAll($data);
 field($pl, PluginBase::class, "config", $config);
 field($pl, PluginBase::class, "server", $server);
 field($pl, PluginBase::class, "logger", new PluginLogger($logger, "SoulWell"));
 $pl->souls = new Config($path."-souls", Config::YAML);
 $pl->souls->set("soulwelltest", 0);
 $pl->rewards = new RewardManager($data, fn(int $min, int $max):int => $min);
 $pl->rewardService = new RewardService($pl);
 return [$pl, new CheckPlayer(), $server, $logger];
}

function rollFixture(Main $pl, CheckPlayer $p, int $count):RollUpdater{
 $roll = (new ReflectionClass(RollUpdater::class))->newInstanceWithoutConstructor();
 $p->window = new SimpleInventory(54);
 foreach(["pl" => $pl, "p" => $p, "rolls" => $count, "columns" => $count === 5 ? [0, 2, 4, 6, 8] : ($count === 3 ? [2, 4, 6] : [4]), "wellMenu" => $p->window, "sent" => true, "opened" => true, "ready" => 9] as $name => $value) field($roll, RollUpdater::class, $name, $value);
 $pl->rolls["soulwelltest"] = $roll;
 return $roll;
}

$playerManager = (new ReflectionClass(PlayerManager::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(InvMenuHandler::class, "player_manager"))->setValue(null, $playerManager);
$legendary = $defaults;
$legendary["rarities"] = ["common" => 0, "rare" => 0, "epic" => 0, "legendary" => 100];
foreach([1, 3, 5] as $count){
 [$pl, $p, $server, $logger] = fixture($legendary, $temp."/roll-".$count);
 $roll = rollFixture($pl, $p, $count);
 $chest = $p->window;
 for($tick = 0; $tick < 500 && isset($pl->rolls["soulwelltest"]); $tick++) $roll->onRun();
 check(!isset($pl->rolls["soulwelltest"]) && $p->window === null, "Roll did not finish and close");
 check(count($p->bag->getContents()) === $count && count($server->messages) === $count, "Multi-roll must deliver and broadcast exactly one reward per reel");
 check(count($p->titles) === 1 && $p->titles[0][0] === "§6§lLEGENDARY!", "Multi-roll must send one highest-rarity title");
 check(count(array_filter($p->testWorld->sounds, fn(Sound $sound):bool => $sound instanceof TotemUseSound)) === 1, "Multi-roll must play the Totem celebration once");
 foreach($chest->getContents() as $slot => $item){
  check(intdiv($slot, 9) === 3 && $item->equalsExact(array_values($p->bag->getContents())[0]), "Winning reel row and delivered item differ");
 }
 $roll->closeInventory($p);
 $roll->abort();
 check(count($p->bag->getContents()) === $count && count($server->messages) === $count && $pl->souls->get("soulwelltest") === 0, "Settled roll was delivered or refunded twice");
 check($logger->errors === [], "Unexpected error during roll");
}
[$pl, $p, $server] = fixture($legendary, $temp."/close");
$roll = rollFixture($pl, $p, 3);
$roll->onRun();
$roll->closeInventory($p);
$roll->closeInventory($p);
check(count($p->bag->getContents()) === 3 && count($server->messages) === 3, "Closing a started roll must settle its three preselected rewards once");
foreach(["before", "disconnect", "shutdown"] as $case){
 [$pl, $p, $server] = fixture($legendary, $temp."/abort-".$case);
 $roll = rollFixture($pl, $p, 5);
 if($case !== "before") $roll->onRun();
 if($case === "disconnect") $p->online = false;
 if($case === "shutdown") $pl->onDisable();
 else $roll->abort();
 $roll->abort();
 check($pl->souls->get("soulwelltest") === 50 && $p->bag->getContents() === [] && $server->messages === [], "Interrupted roll must refund exactly 50 keys without awarding loot");
}
[$pl, $p, $server] = fixture($defaults, $temp."/mixed");
$draw = 0;
$random = function(int $min, int $max) use (&$draw):int{return [9000, 1, 9900, 1, 1, 1][$draw++];};
$manager = new RewardManager($defaults, $random);
$mixed = [$manager->draw(), $manager->draw(), $manager->draw()];
$pl->rewardService->give($p, $mixed);
check(count($server->messages) === 2 && count($p->titles) === 1 && $p->titles[0][0] === "§6§lLEGENDARY!", "Mixed rewards must only broadcast Epic/Legendary and show the highest title");
check(str_contains($server->messages[1], "[Legendary Nautilus Fang") && !str_contains($server->messages[1], "{PLAYER}"), "Broadcast did not replace its placeholders");
[$pl, $p, $server, $logger] = fixture($legendary, $temp."/full");
$stone = \pocketmine\item\StringToItemParser::getInstance()->parse("cobblestone")->setCount(64);
for($i = 0; $i < 36; $i++) $p->bag->setItem($i, $stone);
$p->failTitle = true;
$pl->rewardService->give($p, [$pl->rewards->draw(), $pl->rewards->draw()]);
check(count($p->testWorld->drops) === 2 && count($logger->errors) === 1, "Full inventory or title failure lost a reward");
$quiet = $legendary;
$quiet["celebration"] = ["broadcast" => false, "title" => false, "sound" => false];
[$pl, $p, $server] = fixture($quiet, $temp."/quiet");
$pl->rewardService->give($p, [$pl->rewards->draw()]);
check(count($p->bag->getContents()) === 1 && $server->messages === [] && $p->titles === [] && $p->testWorld->sounds === [], "Disabling celebrations must not disable reward delivery");
echo "PASS 1x/3x/5x reels, exact winning items, single settlement, early close, disconnect/shutdown refunds, broadcast/title/audio targeting and full-inventory safety\n";
