<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$output = $argv[1] ?? $root."/output/SoulWell.phar";
if(file_exists($output)) throw new RuntimeException("Output already exists: ".$output);
if(!is_dir(dirname($output)) && !mkdir(dirname($output), 0755, true)) throw new RuntimeException("Unable to create output directory");
$phar = new Phar($output);
$phar->startBuffering();
foreach(["plugin.yml", "icon.png", "LICENSE"] as $name) $phar->addFile($root."/".$name, $name);
foreach(["src", "resources"] as $dir){
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root."/".$dir, FilesystemIterator::SKIP_DOTS)) as $file){
  if($file->isFile()) $phar->addFile($file->getPathname(), substr($file->getPathname(), strlen($root) + 1));
 }
}
$phar->setStub("<?php __HALT_COMPILER();");
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->stopBuffering();
echo $output."\n";
