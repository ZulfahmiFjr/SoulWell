<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

final class NativeRoll{

    public const WORDS = 2243;
    private static ?\FFI $ffi = null;

    public static function initialize(string $directory):void{
     if(self::$ffi !== null) return;
     if(!extension_loaded("ffi") || PHP_OS_FAMILY !== "Linux" || php_uname("m") !== "x86_64" || PHP_INT_SIZE !== 8) throw new \RuntimeException("SoulWell Rust rolls require PHP FFI on Linux x86_64");
     $root = dirname(__DIR__, 4)."/resources/native/";
     $bytes = file_get_contents($root."libsoulwell_roll.so");
     $hash = trim(file_get_contents($root."roll.sha256"));
     if($bytes === false || !preg_match('/^[a-f0-9]{64}$/D', $hash) || !hash_equals($hash, hash("sha256", $bytes))) throw new \RuntimeException("SoulWell native library checksum mismatch");
     if(!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException("Unable to create SoulWell native directory");
     $path = $directory."/libsoulwell_roll-".$hash.".so";
     if(!is_file($path) || !hash_equals($hash, hash_file("sha256", $path))){
      $temp = tempnam($directory, "roll-");
      if($temp === false) throw new \RuntimeException("Unable to stage SoulWell native library");
      try{
       if(file_put_contents($temp, $bytes) !== strlen($bytes) || !chmod($temp, 0600) || !rename($temp, $path)) throw new \RuntimeException("Unable to extract SoulWell native library");
      }finally{
       if(is_file($temp)) unlink($temp);
      }
     }
     $ffi = \FFI::cdef(file_get_contents($root."roll.h"), $path);
     if($ffi->sw_roll_abi() !== 1) throw new \RuntimeException("Unsupported SoulWell native ABI");
     self::$ffi = $ffi;
    }

    public static function plan(array $config, int $rolls, bool $custom):array{
     if(self::$ffi === null) throw new \RuntimeException("SoulWell native roll backend is unavailable");
     if(!in_array($rolls, [1, 3, 5], true) || count($config) > 2000005) throw new \InvalidArgumentException("Unsupported SoulWell roll plan");
     $input = self::$ffi->new("uint32_t[".count($config)."]");
     $packed = pack("I*", ...$config);
     \FFI::memcpy($input, $packed, strlen($packed));
     $output = self::$ffi->new("int32_t[".self::WORDS."]");
     $status = self::$ffi->sw_roll_plan($input, count($config), $rolls, (int) $custom, $output, self::WORDS);
     if($status !== 0) throw new \RuntimeException("SoulWell native roll failed, status ".$status);
     return array_values(unpack("l*", \FFI::string($output, self::WORDS * 4)));
    }
}
