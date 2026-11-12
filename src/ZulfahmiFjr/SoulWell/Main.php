<?php

declare(strict_types=1);

/* SoulWell Plugin Made By ZulfahmiFjr 2020 */

namespace ZulfahmiFjr\SoulWell;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerResourcePackOfferEvent;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketDecodeEvent;
use pocketmine\event\EventPriority;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\scheduler\ClosureTask;

use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\InvMenuHandler;
use ZulfahmiFjr\SoulWell\event\SoulKeyProvider;
use ZulfahmiFjr\SoulWell\task\RollUpdater;
use ZulfahmiFjr\SoulWell\manager\RewardManager;
use ZulfahmiFjr\SoulWell\service\RewardConfig;
use ZulfahmiFjr\SoulWell\service\RewardService;
use ZulfahmiFjr\SoulWell\service\ResourcePackService;
use ZulfahmiFjr\SoulWell\service\MenuService;
use ZulfahmiFjr\SoulWell\service\LanguageService;

class Main extends PluginBase implements Listener{

    public ?RewardManager $rewards = null;
    public ?RewardService $rewardService = null;
    public $souls;
    public $coords;
    public $set = array();
    public array $rolls = [];
    public ?MenuService $menus = null;
    public ?LanguageService $languages = null;
    private ?ResourcePackService $resourcePack = null;
    private array $floatingTexts = [];

    public function lang(CommandSender $p):string{
     return $this->languages?->get($p) ?? "eng";
    }

    public function text(CommandSender $p, string $key, array $values = []):string{
     return LanguageService::text($this->lang($p), $key, $values);
    }

    public function refreshFloatingText(Player $p):void{
     if(!$this->coords instanceof Config || !$this->coords->exists("x") || !$this->coords->exists("y") || !$this->coords->exists("z")) return;
     $name = strtolower($p->getName());
     $particle = $this->floatingTexts[$name] ??= new FloatingTextParticle("");
     $particle->setTitle($this->text($p, "well.floating"));
     $p->getWorld()->addParticle(new Vector3($this->coords->get("x") + 0.5, $this->coords->get("y") + 2, $this->coords->get("z") + 0.5), $particle, [$p]);
    }

    public function hasCustomUi():bool{
     return $this->resourcePack !== null;
    }

    public function onEnable():void{
     if(!is_dir($this->getDataFolder())) @mkdir($this->getDataFolder());
     $this->getServer()->getPluginManager()->registerEvents($this, $this);
     if(!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);
     // altay membuang paket latency sbelum decode, izinkan balasan invmenu masuk ke listener
     $this->getServer()->getPluginManager()->registerEvent(DataPacketDecodeEvent::class, function(DataPacketDecodeEvent $e):void{
      if($e->getPacketId() === \pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket::NETWORK_ID) $e->uncancel();
     }, EventPriority::NORMAL, $this, true);
     $this->saveResource("config.yml");
     $this->languages = new LanguageService($this->getDataFolder()."languages.yml");
     $this->souls = new Config($this->getDataFolder().'souls.yml', Config::YAML);
     if($this->getServer()->getPluginManager()->getPlugin("ScoreHud") !== null) $this->getServer()->getPluginManager()->registerEvents(new SoulKeyProvider($this), $this);
     $this->coords = new Config($this->getDataFolder().'coords.yml', Config::YAML);
     $this->menus = new MenuService($this);
     try{
      $text = file_get_contents($this->getResourcePath("config.yml"));
      if($text === false) throw new \RuntimeException("Unable to open bundled SoulWell defaults");
      $defaults = yaml_parse($text);
      if(!is_array($defaults)) throw new \RuntimeException("Unable to read bundled SoulWell defaults");
      if(RewardConfig::upgrade($this->getConfig(), $defaults)) $this->getLogger()->info("SoulWell rarity config upgraded, original rewards preserved and config backup saved");
      $this->rewards = new RewardManager($this->getConfig()->getAll());
      $this->rewardService = new RewardService($this);
      $this->getLogger()->info("SoulWell loaded ".$this->rewards->getCount()." rewards, ".$this->rewards->getSummary());
     }catch(\Throwable $e){
      $this->getLogger()->error("SoulWell rolls disabled, fix config and restart: ".$e->getMessage());
     }
     if($this->getConfig()->get("classic-chest-rp")){
      $pack = new ResourcePackService($this);
      $pack->load();
      $this->resourcePack = $pack;
      $this->getServer()->getPluginManager()->registerEvent(PlayerResourcePackOfferEvent::class, function(PlayerResourcePackOfferEvent $e) use ($pack):void{$pack->onOffer($e);}, EventPriority::HIGHEST, $this);
      $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($pack):void{$pack->prioritize();}), 1);
     }
    }

    public function onDisable():void{
     foreach($this->rolls as $roll) $roll->abort();
     $this->souls->save();
     $this->resourcePack?->unload();
     $this->resourcePack = null;
    }

    public function onPlayerJoin(PlayerJoinEvent $e){
     $p = $e->getPlayer();
     if($p instanceof Player){
      if(!$this->souls->exists(strtolower($p->getName()))){
       $this->souls->set(strtolower($p->getName()), 0);
       $this->souls->save();
      }
      $this->refreshFloatingText($p);
     }
    }

    public function onPlayerQuit(PlayerQuitEvent $e){
     $name = strtolower($e->getPlayer()->getName());
     if(isset($this->rolls[$name])) $this->rolls[$name]->abort();
     $this->souls->save();
     unset($this->floatingTexts[$name]);
    }

    public function onPlayerInteract(PlayerInteractEvent $e){
     $p = $e->getPlayer();
     $b = $e->getBlock();
     if($p instanceof Player){
      $data = $this->coords;
      $x = $data->get("x");
      $y = $data->get("y");
      $z = $data->get("z");
      if($b->getPosition()->x === $x && $b->getPosition()->y === $y + 2 && $b->getPosition()->z === $z || $b->getPosition()->x === $x && $b->getPosition()->y === $y + 1 && $b->getPosition()->z === $z){
       if(isset($this->rolls[strtolower($p->getName())])){$e->cancel();return;}
       $this->menus?->confirmation($p);
       $e->cancel();
      }
     }
    }

    public function onBlockBreak(BlockBreakEvent $e){
     $p = $e->getPlayer();
     $b = $e->getBlock();
     $data = $this->coords;
     if(isset($this->set[$p->getName()])){
      $x = $b->getPosition()->x;
      $y = $b->getPosition()->y;
      $z = $b->getPosition()->z;
      if(empty($data->get("x")) && empty($data->get("y")) && empty($data->get("z"))){
       $data->set("x", $x);
       $data->set("y", $y);
       $data->set("z", $z);
       $data->save();
       foreach($this->getServer()->getOnlinePlayers() as $t) $this->refreshFloatingText($t);
       $b->getPosition()->getWorld()->setBlockAt($x, $y + 1, $z, VanillaBlocks::END_PORTAL_FRAME());
       $p->sendMessage($this->text($p, "well.created"));
       unset($this->set[$p->getName()]);
      }else{
       $p->sendMessage($this->text($p, "well.exists"));
       unset($this->set[$p->getName()]);
      }
      $e->cancel();
      return;
     }
     if(!empty($data->get("x")) && !empty($data->get("y")) && !empty($data->get("z"))){
      $x = $data->get("x");
      $y = $data->get("y");
      $z = $data->get("z");
      if($b->getPosition()->x === $x && $b->getPosition()->y === $y + 2 && $b->getPosition()->z === $z || $b->getPosition()->x === $x && $b->getPosition()->y === $y + 1  && $b->getPosition()->z === $z){
       if($this->getServer()->isOp($p->getName())){
        $data->remove("x");
        $data->remove("y");
        $data->remove("z");
        $data->save();
        $p->sendMessage($this->text($p, "well.removed"));
       }else{
        $p->sendMessage($this->text($p, "well.no_permission"));
        $e->cancel();
       }
      }
     }
    }

    public function startRoll(Player $p, int $rolls):void{
     $name = strtolower($p->getName());
     if(!$p->isOnline() || !in_array($rolls, [1, 3, 5], true) || isset($this->rolls[$name])) return;
     if($this->rewards === null || $this->rewardService === null){
      $p->sendMessage($this->text($p, "roll.unavailable"));
      return;
     }
     $cost = $rolls * 10;
     if((int) $this->souls->get($name, 0) < $cost){
      $p->sendMessage($this->text($p, "roll.insufficient"));
      return;
     }
     $roll = new RollUpdater($this, $p, 100, $rolls);
     $this->souls->set($name, (int) $this->souls->get($name, 0) - $cost);
     $this->souls->save();
     $this->rolls[$name] = $roll;
     $this->getScheduler()->scheduleRepeatingTask($roll, 1);
    }

    // benerin huruf kapital otomatis biar pas player ngetik command di hp ga mental, tetep kebaca server
    public function onCommandEvent(CommandEvent $e):void{
     $cmd = $e->getCommand();
     $parts = explode(" ", $cmd, 2);
     $label = strtolower($parts[0]);
     if($label === "soulwell" || $label === "sw"){
      $e->setCommand($label.(isset($parts[1]) ? " ".$parts[1] : ""));
     }
    }

    public function onCommand(CommandSender $p, Command $command, string $label, array $args):bool{
     $sub = isset($args[0]) ? strtolower($args[0]) : "";
     switch($sub){
      case "":{
       $msg = $this->text($p, "command.help");
       if($this->getServer()->isOp($p->getName()) || !$p instanceof Player){
        $msg .= $this->text($p, "command.help_admin");
       }
       if($p instanceof Player) $msg .= $this->text($p, "command.help_lang");
       $p->sendMessage($msg);
       break;
      }
      case "info":{
       $p->sendMessage($this->text($p, "command.info"));
       break;
      }
      case "lang":{
       if(!$p instanceof Player){$p->sendMessage($this->text($p, "command.player_only"));return false;}
       $lang = strtolower($args[1] ?? "");
       if(count($args) !== 2 || !in_array($lang, ["ind", "eng"], true)){$p->sendMessage($this->text($p, "language.usage"));return false;}
       if(isset($this->rolls[strtolower($p->getName())])){$p->sendMessage($this->text($p, "language.busy"));return false;}
       $this->languages->set($p, $lang);
       $p->sendMessage($this->text($p, "language.changed", ["{LANGUAGE}" => $this->text($p, "language.".$lang)]));
       $this->refreshFloatingText($p);
       break;
      }
      case "create":{
       if(!$p instanceof Player){
        $p->sendMessage($this->text($p, "command.player_only"));
        return false;
       }
       if(!$this->getServer()->isOp($p->getName())){
        $p->sendMessage($this->text($p, "command.no_permission"));
        return false;
       }
       $this->set[$p->getName()] = true;
       $p->sendMessage($this->text($p, "command.create"));
       break;
      }
      case "key":{
       if(!$this->getServer()->isOp($p->getName()) && $p instanceof Player){
        $p->sendMessage($this->text($p, "command.no_permission"));
        return false;
       }
       if(!isset($args[1])){
        $p->sendMessage($this->text($p, "command.key_player"));
        return false;
       }
       if(!isset($args[2])){
        $p->sendMessage($this->text($p, "command.key_count"));
        return false;
       }
       if(!is_numeric($args[2]) || (int) $args[2] <= 0){
        $p->sendMessage($this->text($p, "command.key_invalid"));
        return false;
       }
       $t = $this->getServer()->getPlayerExact($args[1]);
       if($t instanceof Player){
        $t->sendMessage($this->text($t, "command.key_received", ["{AMOUNT}" => $args[2]]));
        $name = strtolower($t->getName());
       }else{
        $name = strtolower($args[1]);
       }
       $this->souls->set($name, (int) $this->souls->get($name, 0) + (int) $args[2]);
       $this->souls->save();
       $p->sendMessage($this->text($p, "command.key_added", ["{PLAYER}" => $name, "{AMOUNT}" => $args[2]]));
       break;
      }
      default:{
       $p->sendMessage($this->text($p, "command.usage"));
       break;
      }
     }
     return true;
    }

}
