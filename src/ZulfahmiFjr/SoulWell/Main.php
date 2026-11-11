<?php

/* SoulWell Plugin Made By ZulfahmiFjr 2020 */

namespace ZulfahmiFjr\SoulWell;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\event\server\DataPacketDecodeEvent;
use pocketmine\event\EventPriority;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\scheduler\ClosureTask;

use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\InvMenuHandler;
use ZulfahmiFjr\SoulWell\event\SoulKeyProvider;
use ZulfahmiFjr\SoulWell\task\RollUpdater;

class Main extends PluginBase implements Listener{

    public $wellItems;
    public $souls;
    public $coords;
    public $set = array();
    public array $rolls = [];
    private array $forms = [];

    private function loadPack(){
        $manager = $this->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($this->getDataFolder()."resource.zip");
        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty("resourcePacks");
        $property->setAccessible(true);
        $currentResourcePacks = $property->getValue($manager);
        $currentResourcePacks[] = $pack;
        $property->setValue($manager, $currentResourcePacks);
        $property = $reflection->getProperty("uuidList");
        $property->setAccessible(true);
        $currentUUIDPacks = $property->getValue($manager);
        $currentUUIDPacks[strtolower($pack->getPackId())] = $pack;
        $property->setValue($manager, $currentUUIDPacks);
        $property = $reflection->getProperty("serverForceResources");
        $property->setAccessible(true);
        $property->setValue($manager, true);
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
     $this->wellItems = $this->getConfig()->get("items");
     $this->souls = new Config($this->getDataFolder().'souls.yml', Config::YAML);
     if($this->getServer()->getPluginManager()->getPlugin("ScoreHud") !== null) $this->getServer()->getPluginManager()->registerEvents(new SoulKeyProvider($this), $this);
     $this->coords = new Config($this->getDataFolder().'coords.yml', Config::YAML);
     if($this->getConfig()->get("classic-chest-rp")){
      $zip = new \ZipArchive();
      $zip->open(Path::join($this->getDataFolder(), "resource.zip"), \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
      foreach($this->getResources() as $resource){
       if($resource->isFile() && str_contains($resource->getPathname(), 'ClassicLargeChest')){
        $relativePath = Path::normalize(preg_replace("/.*[\/\\\\]ClassicLargeChest[\/\\\\].*/U", '', $resource->getPathname()));
        $this->saveResource(Path::join('ClassicLargeChest', $relativePath), true);
        $zip->addFile(Path::join($this->getDataFolder(), 'ClassicLargeChest', $relativePath), $relativePath);
       }
      }
      $zip->close();
      Filesystem::recursiveUnlink(Path::join($this->getDataFolder().'ClassicLargeChest'));
      $this->loadPack();
     }
    }

    public function onDisable():void{
     foreach($this->rolls as $roll) $roll->abort();
     $this->souls->save();
     if($this->getConfig()->get("classic-chest-rp")){
      $manager = $this->getServer()->getResourcePackManager();
      $resourcePack = new ZippedResourcePack($this->getDataFolder()."resource.zip");
      $reflection = new \ReflectionClass($manager);
      $property = $reflection->getProperty("resourcePacks");
      $property->setAccessible(true);
      $currentResourcePacks = $property->getValue($manager);
      $key = array_search($resourcePack, $currentResourcePacks, true);
      if($key !== false){
       unset($currentResourcePacks[$key]);
       $property->setValue($manager, $currentResourcePacks);
      }
      $property = $reflection->getProperty("uuidList");
      $property->setAccessible(true);
      $currentUUIDPacks = $property->getValue($manager);
      if(isset($currentResourcePacks[strtolower($resourcePack->getPackId())])) {
       unset($currentUUIDPacks[strtolower($resourcePack->getPackId())]);
       $property->setValue($manager, $currentUUIDPacks);
      }
     }
    }

    public function onPlayerJoin(PlayerJoinEvent $e){
     $p = $e->getPlayer();
     if($p instanceof Player){
      if(!$this->souls->exists(strtolower($p->getName()))){
       $this->souls->set(strtolower($p->getName()), 0);
       $this->souls->save();
      }
      $data = $this->coords;
      $x = $data->get("x");
      $y = $data->get("y");
      $z = $data->get("z");
      $text = "§bSoul Well\n§l§eRIGHT CLICK";
      $p->getWorld()->addParticle(new Vector3($x + 0.5, $y + 2, $z + 0.5), new FloatingTextParticle('', $text), array($p));
     }
    }

    public function onPlayerQuit(PlayerQuitEvent $e){
     $name = strtolower($e->getPlayer()->getName());
     if(isset($this->rolls[$name])) $this->rolls[$name]->abort();
     unset($this->forms[$name]);
     $this->souls->save();
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
       $pk = new ModalFormRequestPacket();
       $pk->formId = 7382999;
       $message = "§f       _________________________\n           §6§lSoul Well by Hypixel\n§r§f       -------------------------\n";
       $messageData = $this->getConfig()->get("message");
       if(!empty($messageData)){
        if(is_array($messageData)){
         foreach($messageData as $text){
          $text = str_replace(["{KEY}", "{PLAYER}"], [$this->souls->get(strtolower($p->getName())), $p->getName()], $text);
          $message .= "{$text}§r\n\n";
         }
        }else if(is_string($messageData)){
         $text = str_replace(["{KEY}", "{PLAYER}"], [$this->souls->get(strtolower($p->getName())), $p->getName()], $messageData);
         $message .= "{$text}§r\n\n";
        }
       }
       $encode = ["type" => "form", "title" => "§e§lSoul Well Confirm", "content" => "{$message}", "buttons" => [["text" => "§l1x Roll §r§6(10 Soul Keys)"], ["text" => "§l3x Roll §r§6(30 Soul Keys)"], ["text" => "§l5x Roll §r§6(50 Soul Keys)"], ["text" => "§lCancel Opening"]]];
       $data = json_encode($encode);
       $pk->formData = $data;
       $this->forms[strtolower($p->getName())] = true;
       $p->getNetworkSession()->sendDataPacket($pk);
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
       $text = "§bSoul Well\n§l§eRIGHT CLICK";
       $b->getPosition()->getWorld()->addParticle(new Vector3($x + 0.5, $y + 2, $z + 0.5), new FloatingTextParticle('', $text));
       $b->getPosition()->getWorld()->setBlockAt($x, $y + 1, $z, VanillaBlocks::END_PORTAL_FRAME());
       $p->sendMessage("§f§lSoulWell§r§f: §7§oSoulWell successfully added§r§f.");
       unset($this->set[$p->getName()]);
      }else{
       $p->sendMessage("§f§lSoulWell§r§f: §7§oSoul Well has been made please delete first§r§f.");
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
        $p->sendMessage("§f§lSoulWell§r§f: §7§oSoulWell successfully remove§r§f!");
       }else{
        $p->sendMessage("§f§lSoulWell§r§f: §7§oYou have no permission to break SoulWell§r§f!");
        $e->cancel();
       }
      }
     }
    }

    public function onPacketReceive(DataPacketReceiveEvent $e){
     $pk = $e->getPacket();
     $p = $e->getOrigin()->getPlayer();
     if($pk instanceof ModalFormResponsePacket && $p instanceof Player){
      $id = $pk->formId;
      $name = strtolower($p->getName());
      if($id !== 7382999 || !isset($this->forms[$name])) return;
      unset($this->forms[$name]);
      if(isset($this->rolls[$name])) return;
      $data = json_decode($pk->formData ?? "[]", true);
      if($id === 7382999 && isset($data) && is_int($data)){
       $rolls = match($data){ 0 => 1, 1 => 3, 2 => 5, default => 0 };
       if($rolls > 0){
        $cost = $rolls * 10;
        if((int) $this->souls->get(strtolower($p->getName()), 0) < $cost){
         $p->sendMessage("§f§lSoulWell§r§f: §7§oYour Soul Keys amount is still lacking to open SoulWell§r§f!");
         return;
        }
        $this->souls->set(strtolower($p->getName()), (int) $this->souls->get(strtolower($p->getName()), 0) - $cost);
        $this->souls->save();
        $roll = new RollUpdater($this, $p, 100, $rolls);
        $this->rolls[$name] = $roll;
        $this->getScheduler()->scheduleRepeatingTask($roll, 3);
       }
      }
     }
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
       $msg = "§l§9»» §r§e§oSoulWell Commands§r§f:\n§f- §e§o/sw info§r§f: §7§oDisplay plugin information§r§f";
       if($this->getServer()->isOp($p->getName()) || !$p instanceof Player){
        $msg .= "\n§f- §e§o/sw create§r§f: §7§oAdd SoulWell block§r§f\n§f- §e§o/sw key [player-name] [count]§r§f: §7§oAdd Soul Keys to player§r§f";
       }
       $p->sendMessage($msg);
       break;
      }
      case "info":{
       $p->sendMessage("§l§9»» §r§e§oHi this plugin made by ZulfahmiFjr§r§f, §e§oyou can contact me at§r§f:\n§f- §e§oDiscord§r§f: ZulfahmiFjr#8525\n§f- §e§oWhatsapp§r§f: +6287880267100\n§f- §e§oEmail§r§f: 6931856cg@gmail.com\n§e§oPlease take care this plugin§r§f! §e§oThanks§r§f :)");
       break;
      }
      case "create":{
       if(!$p instanceof Player){
        $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease use this command in the game§r§f!");
        return false;
       }
       if(!$this->getServer()->isOp($p->getName())){
        $p->sendMessage("§f§lSoulWell§r§f: §7§oYou have no permission to use this command§r§f!");
        return false;
       }
       $this->set[$p->getName()] = true;
       $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease destroy 1 block§r§f!");
       break;
      }
      case "key":{
       if(!$this->getServer()->isOp($p->getName()) && $p instanceof Player){
        $p->sendMessage("§f§lSoulWell§r§f: §7§oYou have no permission to use this command§r§f!");
        return false;
       }
       if(!isset($args[1])){
        $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease use command§r§f: /sw key [player-name]");
        return false;
       }
       if(!isset($args[2])){
        $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease use command§r§f: /sw key [player-name] [count]");
        return false;
       }
       if(!is_numeric($args[2]) || (int) $args[2] <= 0){
        $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease enter the number of soul keys correctly§r§f!");
        return false;
       }
       $t = $this->getServer()->getPlayerExact($args[1]);
       if($t instanceof Player){
        $t->sendMessage("§f§lSoulWell§r§f: §7§oYou have sent Soul Keys to the amount§r§f ".$args[2].".");
        $name = strtolower($t->getName());
       }else{
        $name = strtolower($args[1]);
       }
       $this->souls->set($name, (int) $this->souls->get($name, 0) + (int) $args[2]);
       $this->souls->save();
       $p->sendMessage("§f§lSoulWell§r§f: §7§oYou have successfully added §r§f".$name." §7§oSoul Keys to the amount §r§f".$args[2].".");
       break;
      }
      default:{
       $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease use command§r§f: /sw [info|create|key]");
       break;
      }
     }
     return true;
    }

}
