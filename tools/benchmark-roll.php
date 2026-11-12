<?php

declare(strict_types=1);

use pocketmine\inventory\CallbackInventoryListener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\network\mcpe\InventoryManager;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pmmp\encoding\ByteBufferWriter;
use ZulfahmiFjr\SoulWell\manager\RewardManager;

require __DIR__."/check-rewards.php";

final class BenchNetwork extends NetworkSession{

    public int $packets = 0;
    public int $bytes = 0;

    public function __construct(){
    }

    public function getLogger():Logger{
     return new CheckLogger();
    }

    public function getTypeConverter():TypeConverter{
     return TypeConverter::getInstance();
    }

    public function sendDataPacket(ClientboundPacket $packet, bool $immediate = false):bool{
     $buffer = new ByteBufferWriter();
     $packet->encode($buffer);
     $this->packets++;
     $this->bytes += strlen($buffer->getData());
     return true;
    }
}

$temp = sys_get_temp_dir()."/soulwell-bench-".getmypid();
mkdir($temp);
$result = [];
TypeConverter::getInstance();
try{
 foreach([false, true] as $network){
  foreach([1, 3, 5] as $count){
   [$pl, $p] = fixture($defaults, $temp."/bench-".(int) $network."-".$count);
   $pl->rewards = new RewardManager($defaults);
   $elapsed = $changes = $packets = $bytes = 0;
   for($n = 0; $n < 30; $n++){
    $p->bag->clearAll();
    $p->messages = $p->titles = $p->testWorld->sounds = [];
    $begin = hrtime(true);
    $roll = rollFixture($pl, $p, $count);
    $p->window->getListeners()->add(CallbackInventoryListener::onAnyChange(function(Inventory $inventory) use (&$changes):void{$changes++;}));
    if($network){
     $session = new BenchNetwork();
     $manager = (new ReflectionClass(InventoryManager::class))->newInstanceWithoutConstructor();
     field($manager, InventoryManager::class, "player", $p);
     field($manager, InventoryManager::class, "session", $session);
     (new ReflectionMethod(InventoryManager::class, "add"))->invoke($manager, 1, $p->window);
     $manager->syncContents($p->window);
     $session->packets = $session->bytes = 0;
     $p->window->getListeners()->add(new CallbackInventoryListener(function(Inventory $inv, int $slot, Item $old) use ($manager):void{$manager->onSlotChange($inv, $slot);}, function(Inventory $inv, array $old) use ($manager):void{$manager->syncContents($inv);}));
    }
    for($tick = 0; $tick < 500 && isset($pl->rolls["soulwelltest"]); $tick++){
     $roll->onRun();
     if($network) $manager->flushPendingUpdates();
    }
    $elapsed += hrtime(true) - $begin;
    if($network){$packets += $session->packets;$bytes += $session->bytes;}
   }
   $result[$network ? "protocol" : "inventory"][$count] = ["roll_ms" => round($elapsed / 30e6, 4), "inventory_callbacks" => $changes / 30, "packets" => $packets / 30, "bytes" => round($bytes / 30)];
  }
 }
 echo "BENCHMARK ".json_encode($result)."\n";
}finally{
 foreach(glob($temp."/*") as $file) unlink($file);
 rmdir($temp);
}
