<?php

declare(strict_types=1);

use pocketmine\inventory\SimpleInventory;
use ZulfahmiFjr\SoulWell\Main;
use ZulfahmiFjr\SoulWell\service\NativeRoll;
use ZulfahmiFjr\SoulWell\service\RollPlan;

$delays = [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 5, 5, 5, 7, 9, 12, 16, 22];
foreach([1, 3, 5] as $count){
 foreach([true, false] as $custom){
  $plan = new RollPlan(new \ZulfahmiFjr\SoulWell\manager\RewardManager($defaults), $count, $custom);
  check($plan->delays === $delays, "Native roll changed deceleration timing");
  $inventory = new SimpleInventory(54);
  foreach($plan->frames as $step => $changes){
   check($plan->notes[$step] === ($step >= 34 ? [14, 16, 18, 20, 24][$step - 34] : [5, 2, 9, 12][$step % 4]), "Native roll changed note pitch");
   $previous = $inventory->getContents(true);
   foreach($changes as $slot => $item){
    check(!$item->equalsExact($previous[$slot]), "Native plan resent an identical slot");
    $inventory->setItem($slot, $item);
   }
   foreach($plan->predictions as $column => $reward){
    if($step === 35) check($inventory->getItem($column)->equalsExact($reward->getItem()), "Winner entered the wrong animation step");
    if($step === 38) check($inventory->getItem($column + 27)->equalsExact($reward->getItem()), "Winner did not reach the selection row");
   }
  }
  foreach($plan->finished as $slot => $item) $inventory->setItem($slot, $item);
  foreach($plan->predictions as $column => $reward){
   check($inventory->getItem($column + 27)->equalsExact($reward->getItem()), "Final frame changed the winning reward");
   foreach([0, 1, 2, 4, 5] as $row) check($inventory->getItem($column + 9 * $row)->isNull(), "Final frame left an extra reward");
  }
  check($custom || count($inventory->getContents()) === 54 - $count * 5, "Vanilla filler slots were lost");
 }
 [$pl, $p] = fixture($defaults, $temp."/timing-".$count);
 $roll = rollFixture($pl, $p, $count);
 $soundTicks = [];
 for($tick = 0; $tick < 500 && isset($pl->rolls["soulwelltest"]); $tick++){
  $previous = count($p->testWorld->sounds);
  $roll->onRun();
  if(count($p->testWorld->sounds) > $previous && end($p->testWorld->sounds) instanceof \ZulfahmiFjr\SoulWell\manager\NoteBlockSound) $soundTicks[] = $tick;
 }
 $expected = [0];
 foreach(array_slice($delays, 0, -1) as $delay) $expected[] = end($expected) + $delay;
 check($soundTicks === $expected && $tick === array_sum(array_slice($delays, 0, -1)) + 17, "Native updater changed tick cadence or finish pause");
}
foreach([[9999, 0, 0, 0, 1, 0, 1], [10000, 0, 0, 0, 1, 4, 1], [10000, 0, 0, 0, 1, 1, 1]] as $bad){
 try{
  NativeRoll::plan($bad, 1, true);
  throw new LogicException("Invalid native config was accepted");
 }catch(RuntimeException $e){
  check(str_contains($e->getMessage(), "status 1"), "Unexpected native validation error");
 }
}
[$pl, $p, $server, $logger] = fixture($defaults, $temp."/native-unavailable");
$pl->souls->set("soulwelltest", 100);
$backend = new ReflectionProperty(NativeRoll::class, "ffi");
$ffi = $backend->getValue();
try{
 $backend->setValue(null, null);
 (new ReflectionMethod(Main::class, "startRoll"))->invoke($pl, $p, 5);
 check($pl->souls->get("soulwelltest") === 100 && $pl->rolls === [] && count($logger->errors) === 1 && count($p->messages) === 1, "Native failure charged keys or started an unsafe roll");
}finally{
 $backend->setValue(null, $ffi);
}
echo "PASS Rust FFI frame/tick parity, minimal slot changes, vanilla fillers, immutable winners and fail-closed key protection\n";
