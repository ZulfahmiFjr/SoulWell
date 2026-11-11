<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\event\player\PlayerResourcePackOfferEvent;
use ZulfahmiFjr\SoulWell\Main;
use ZulfahmiFjr\SoulWell\manager\PrizeMenu;

final class ResourcePackService{

    private ?ZippedResourcePack $pack = null;
    private bool $required = false;

    public function __construct(private Main $pl){
    }

    public function load():void{
     $manager = $this->pl->getServer()->getResourcePackManager();
     $stack = $manager->getResourceStack();
     $defs = [];
     $main = null;
     $serverForm = ["namespace" => "server_form"];
     $fallback = "@server_form.long_form";
     foreach($stack as $pack){
      if(!$pack instanceof ZippedResourcePack) continue;
      $zip = new \ZipArchive();
      if($zip->open($pack->getPath()) !== true) throw new \RuntimeException("Unable to read resource pack ".$pack->getPackName());
      try{
       $text = $zip->getFromName("ui/_ui_defs.json");
       if($text !== false){
        $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        foreach($data["ui_defs"] ?? [] as $file) $defs[] = $file;
       }
       $text = $zip->getFromName("ui/server_form.json");
       if($main === null && $text !== false){
        $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        if(isset($data["main_screen_content"]["controls"])){
         $main = $data["main_screen_content"];
         $serverForm = $data;
        }
       }
      }finally{
       $zip->close();
      }
     }
     $main ??= ["type" => "panel", "size" => [0, 0], "controls" => [["server_form_factory" => ["type" => "factory", "control_ids" => ["long_form" => "@server_form.long_form", "custom_form" => "@server_form.custom_form"]]]]];
     $found = false;
     foreach($main["controls"] as &$control){
      foreach($control as &$entry){
       if(($entry["type"] ?? null) !== "factory" || !isset($entry["control_ids"]["long_form"])) continue;
       $fallback = "@".ltrim($entry["control_ids"]["long_form"], "@");
       $entry["control_ids"]["long_form"] = "@soulwell.form_dispatch";
       $found = true;
      }
      unset($entry);
     }
     unset($control);
     if(!$found) throw new \RuntimeException("Unable to locate the existing server form factory");
     $files = [];
     $prefix = "resources/ClassicLargeChest/";
     foreach($this->pl->getResources() as $resource){
      if(!$resource->isFile()) continue;
      $path = str_replace("\\", "/", $resource->getPathname());
      $pos = strpos($path, $prefix);
      if($pos === false) continue;
      $name = substr($path, $pos + strlen($prefix));
      $text = file_get_contents($resource->getPathname());
      if($text === false) throw new \RuntimeException("Unable to read bundled UI asset ".$name);
      $files[$name] = $text;
     }
     if(!isset($files["manifest.json"], $files["ui/soulwell_ui.json"])) throw new \RuntimeException("SoulWell UI assets are missing");
     $ui = json_decode($files["ui/soulwell_ui.json"], true, 512, JSON_THROW_ON_ERROR);
     if($this->pl->rewards !== null) PrizeMenu::configureUi($ui, $this->pl->rewards);
     $ui["form_dispatch"]["controls"][0] = ["original".$fallback => $ui["form_dispatch"]["controls"][0]["original@server_form.long_form"]];
     $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;
     $files["ui/soulwell_ui.json"] = json_encode($ui, $flags);
     $serverForm["main_screen_content"] = $main;
     $files["ui/server_form.json"] = json_encode($serverForm, $flags);
     $ownDefs = json_decode($files["ui/_ui_defs.json"], true, 512, JSON_THROW_ON_ERROR)["ui_defs"];
     $files["ui/_ui_defs.json"] = json_encode(["ui_defs" => array_values(array_unique([...$defs, ...$ownDefs]))], $flags);
     ksort($files);
     $path = $this->pl->getDataFolder()."resource.zip";
     $temp = $path.".tmp";
     $zip = new \ZipArchive();
     if($zip->open($temp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException("Unable to build SoulWell resource pack");
     try{
      foreach($files as $name => $text){
       if(!$zip->addFromString($name, $text)) throw new \RuntimeException("Unable to write SoulWell UI asset ".$name);
      }
     }finally{
      $zip->close();
     }
     $pack = new ZippedResourcePack($temp);
     if(!rename($temp, $path)) throw new \RuntimeException("Unable to replace SoulWell resource pack");
     $this->pack = new ZippedResourcePack($path);
     $this->required = $manager->resourcePacksRequired();
     $manager->setResourceStack([$this->pack, ...$stack]);
     $manager->setResourcePacksRequired(true);
     $this->pl->getLogger()->info("Nautic SoulWell UI ".$this->pack->getPackVersion()." ready, other forms and chest layouts preserved");
    }

    public function prioritize():void{
     if($this->pack === null) return;
     $manager = $this->pl->getServer()->getResourcePackManager();
     $stack = $this->prioritized($manager->getResourceStack());
     $manager->setResourceStack($stack);
     $this->report($stack, "startup");
    }

    public function onOffer(PlayerResourcePackOfferEvent $e):void{
     if($this->pack === null) return;
     $stack = $this->prioritized($e->getResourcePacks());
     $e->setResourcePacks($stack, $e->getEncryptionKeys());
     $this->report($stack, "client-offer");
    }

    private function prioritized(array $stack):array{
     if($this->pack === null) return $stack;
     $id = strtolower($this->pack->getPackId());
     return [$this->pack, ...array_values(array_filter($stack, fn($pack):bool => strtolower($pack->getPackId()) !== $id))];
    }

    private function report(array $stack, string $phase):void{
     $rows = array_map(fn($pack):array => ["uuid" => $pack->getPackId(), "name" => $pack->getPackName(), "version" => $pack->getPackVersion(), "sha256" => bin2hex($pack->getSha256()), "path" => $pack instanceof ZippedResourcePack ? $pack->getPath() : null], $stack);
     $data = ["phase" => $phase, "utc" => gmdate("c"), "soulwellFirst" => $this->pack !== null && ($stack[0] ?? null) === $this->pack, "packs" => $rows];
     if(file_put_contents($this->pl->getDataFolder()."resource-pack-".$phase.".json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) $this->pl->getLogger()->warning("Unable to record SoulWell resource pack order");
    }

    public function unload():void{
     if($this->pack === null) return;
     $manager = $this->pl->getServer()->getResourcePackManager();
     $manager->setResourceStack(array_values(array_filter($manager->getResourceStack(), fn($pack):bool => $pack !== $this->pack)));
     $manager->setResourcePacksRequired($this->required);
     $this->pack = null;
    }
}
