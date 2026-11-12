<?php

declare(strict_types=1);

$archive = realpath($argv[1] ?? throw new InvalidArgumentException("PHAR path required"));
require $argv[2] ?? dirname(__DIR__, 2)."/altay/vendor/autoload.php";
spl_autoload_register(function(string $class) use ($archive):void{
 $prefix = "ZulfahmiFjr\\SoulWell\\";
 if(str_starts_with($class, $prefix)) require "phar://".$archive."/src/ZulfahmiFjr/SoulWell/".str_replace("\\", "/", substr($class, strlen($prefix))).".php";
});
$metadata = yaml_parse(file_get_contents("phar://".$archive."/plugin.yml"));
if($metadata["version"] !== "1.11.0") throw new RuntimeException("Unexpected PHAR version");
\ZulfahmiFjr\SoulWell\service\NativeRoll::initialize($argv[3] ?? sys_get_temp_dir()."/soulwell-phar-native-check");
foreach(["phar://".$archive."/resources/config.yml", dirname(__DIR__, 2)."/altay/plugin_data/SoulWell/config.yml"] as $config){
 $manager = new \ZulfahmiFjr\SoulWell\manager\RewardManager(yaml_parse(file_get_contents($config)));
 foreach([1, 3, 5] as $count){
  $plan = new \ZulfahmiFjr\SoulWell\service\RollPlan($manager, $count, true);
  if(count($plan->predictions) !== $count || count($plan->frames) !== 39) throw new RuntimeException("Invalid packaged native plan");
 }
}
echo "PASS packaged v".$metadata["version"].", PHAR native extraction, ABI and default/production reward configuration\n";
