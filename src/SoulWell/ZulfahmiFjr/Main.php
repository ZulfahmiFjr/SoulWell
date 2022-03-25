<?php

namespace SoulWell\ZulfahmiFjr;

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;

use pocketmine\utils\Config;

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

use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

use pocketmine\command\CommandSender;

use pocketmine\command\Command;

use skymin\InventoryLib\InvLibManager;

use SoulWell\ZulfahmiFjr\task\RollUpdater;

class Main extends PluginBase implements Listener{

    public $set = array();

    public function onEnable():void{

     if(!is_dir($this->getDataFolder())) @mkdir($this->getDataFolder());

      $this->getServer()->getPluginManager()->registerEvents($this, $this);

      InvLibManager::register($this);

      $this->saveResource("config.yml");

      $this->wellItems = $this->getConfig()->get("items");

      $this->souls = new Config($this->getDataFolder().'souls.yml', Config::YAML);

      $this->getLogger()->info("SoulWell Plugin Made By ZulfahmiFjr");

     }

    public function onPlayerJoin(PlayerJoinEvent $e){

     $p = $e->getPlayer();

     if($p instanceof Player){

      if(!$this->souls->exists(strtolower($p->getName()))){

       $this->souls->set(strtolower($p->getName()), 0);

       $this->souls->save();

      }

      $data = $this->getConfig();

      $x = $data->get("well.x");

      $y = $data->get("well.y");

      $z = $data->get("well.z");

      $text = "§bSoul Well\n§l§eRIGHT CLICK";

      $p->getWorld()->addParticle(new Vector3($x + 0.5, $y + 2, $z + 0.5), new FloatingTextParticle('', $text), array($p));

     }

    }

    public function onPlayerQuit(PlayerQuitEvent $e){

     $this->souls->save();

    }

    public function onPlayerInteract(PlayerInteractEvent $e){

     $p = $e->getPlayer();

     $b = $e->getBlock();

     if($p instanceof Player){

      $data = $this->getConfig();

      $x = $data->get("well.x");

      $y = $data->get("well.y");

      $z = $data->get("well.z");

      if($b->getPosition()->x === $x && $b->getPosition()->y === $y + 2 && $b->getPosition()->z === $z || $b->getPosition()->x === $x && $b->getPosition()->y === $y + 1 && $b->getPosition()->z === $z){

       $pk = new ModalFormRequestPacket();

       $pk->formId = 7382999;

       $message = "§f       _________________________\n           §6§lSoul Well by Hypixel\n§r§f       -------------------------\n";

       if(!empty($this->getConfig()->get("message"))){

        if(is_array($this->getConfig()->get("message"))){

         foreach($this->getConfig()->get("message") as $text){

          $text = str_replace(["{KEY}", "{PLAYER}"], [$this->souls->get(strtolower($p->getName())), $p->getName()], $text);

          $message .= "{$text}§r\n\n";

         }

        }else{

         $text = str_replace(["{KEY}", "{PLAYER}"], [$this->souls->get(strtolower($p->getName())), $p->getName()], $this->getConfig()->get("message"));

         $message .= "{$text}§r\n\n";

        }

       }

       $encode = ["type" => "form", "title" => "§e§lSoul Well Confirm", "content" => "{$message}", "buttons" => [["text" => "§lOpen SoulWell"], ["text" => "§lCancel Opening"]]];

       $data = json_encode($encode);

       $pk->formData = $data;

       $p->getNetworkSession()->sendDataPacket($pk);

       $e->cancel();

      }

     }

    }

    public function onBlockBreak(BlockBreakEvent $e){

     $p = $e->getPlayer();

     $b = $e->getBlock();

     if(isset($this->set[$p->getName()])){

      $x = $b->getPosition()->x;

      $y = $b->getPosition()->y;

      $z = $b->getPosition()->z;

      $data = $this->getConfig();

      if(empty($data->get("well.x")) && empty($data->get("well.y")) && empty($data->get("well.z"))){

       $data->set("well.x", $x);

       $data->set("well.y", $y);

       $data->set("well.z", $z);

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

     $data = $this->getConfig();

     if(!empty($data->get("well.x")) && !empty($data->get("well.y")) && !empty($data->get("well.z"))){

      $x = $data->get("well.x");

      $y = $data->get("well.y");

      $z = $data->get("well.z");

      if($b->getPosition()->x === $x && $b->getPosition()->y === $y + 2 && $b->getPosition()->z === $z || $b->getPosition()->x === $x && $b->getPosition()->y === $y + 1  && $b->getPosition()->z === $z){

       if($this->getServer()->isOp($p->getName())){

        $data->remove("well.x");

        $data->remove("well.y");

        $data->remove("well.z");

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

     if($pk instanceof ModalFormResponsePacket){

      $id = $pk->formId;

      $data = json_decode($pk->formData, true);

      if($id === 7382999){

       if(isset($data)){

        if($data === 0){

         if($this->souls->get(strtolower($p->getName())) < 10){

          $p->sendMessage("§f§lSoulWell§r§f: §7§oYour Soul Keys amount is still lacking to open SoulWell§r§f!");

          return;

         }

         $this->souls->set(strtolower($p->getName()), $this->souls->get(strtolower($p->getName())) - 10);

         $this->souls->save();

         $this->getScheduler()->scheduleRepeatingTask(new RollUpdater($this, $p, 100), 3);

        }

       }

      }

     }

    }

    public function onCommand(CommandSender $p, Command $command, string $label, array $args):bool{

     switch($command->getName()){

      case "soulwell":{

       $p->sendMessage("§l§9»» §r§e§oHi this plugin made by ZulfahmiFjr§r§f, §e§oyou can contact me at§r§f:\n§f- §e§oDiscord§r§f: ZulfahmiFjr#8525\n§f- §e§oWhatsapp§r§f: +6287880267100\n§f- §e§oEmail§r§f: 6931856cg@gmail.com\n§e§oPlease take care this plugin§r§f! §e§oThanks§r§f :)");

       break;

      }

      case "addwell":{

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

      case "addsouls":{

       if(!$this->getServer()->isOp($p->getName())){

        $p->sendMessage("§f§lSoulWell§r§f: §7§oYou have no permission to use this command§r§f!");

        return false;

       }

       if(!isset($args[0])){

        $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease use command§r§f: /addsouls [player-name]");

        return false;

       }

       if(!isset($args[1])){

        $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease use command§r§f: /addsouls [player-name] [count]");

        return false;

       }

       if(!is_numeric($args[1]) && $args[1] <= 0){

        $p->sendMessage("§f§lSoulWell§r§f: §7§oPlease enter the number of soul keys correctly§r§f!");

        return false;

       }

       $t = $this->getServer()->getPlayerExact($args[0]);

       if($t instanceof Player){

        $t->sendMessage("§f§lSoulWell§r§f: §7§oYou have sent Soul Keys to the amount§r§f ".$args[1].".");

        $name = strtolower($t->getName());

       }else{

        $name = strtolower($args[0]);

       }

       $this->souls->set($name, $this->souls->get($name) + $args[1]);

       $this->souls->save();

       $p->sendMessage("§f§lSoulWell§r§f: §7§oYou have successfully added §r§f".$name." §7§oSoul Keys to the amount §r§f".$args[1].".");

       break;

      }

     }

     return true;

    }

}

