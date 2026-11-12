<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

use pocketmine\command\CommandSender;
use pocketmine\utils\Config;

final class LanguageService{

    public const IND_MARKER = "§0§0§r";
    private static array $catalogs = [];
    private static array $patterns = [];
    private Config $players;

    public function __construct(string $path){
     $this->players = new Config($path, Config::YAML);
    }

    public function get(CommandSender|string $p):string{
     $lang = $this->players->get(strtolower($p instanceof CommandSender ? $p->getName() : $p), "eng");
     return $lang === "ind" ? "ind" : "eng";
    }

    public function set(CommandSender|string $p, string $lang):bool{
     if(!in_array($lang, ["ind", "eng"], true)) return false;
     $this->players->set(strtolower($p instanceof CommandSender ? $p->getName() : $p), $lang);
     $this->players->save();
     return true;
    }

    private static function catalog(string $lang):array{
     $lang = $lang === "ind" ? "ind" : "eng";
     if(!isset(self::$catalogs[$lang])) self::$catalogs[$lang] = json_decode(file_get_contents(dirname(__DIR__, 4)."/resources/languages/".$lang.".json"), true, 512, JSON_THROW_ON_ERROR);
     return self::$catalogs[$lang];
    }

    public static function text(string $lang, string $key, array $values = []):string{
     $text = self::catalog($lang)["messages"][$key] ?? throw new \InvalidArgumentException("Unknown SoulWell language key: ".$key);
     return strtr($text, $values);
    }

    public static function literal(string $lang, string $text):string{
     if($lang !== "ind") return $text;
     $words = self::catalog($lang)["literals"];
     if(!isset(self::$patterns[$lang])){
      $keys = array_keys($words);
      usort($keys, fn(string $a, string $b):int => strlen($b) <=> strlen($a));
      self::$patterns[$lang] = '/(?<![\p{L}\p{N}_])(?:'.implode("|", array_map(fn(string $key):string => preg_quote($key, '/'), $keys)).')(?![\p{L}\p{N}_])/u';
     }
     $parts = preg_split('/(§[0-9a-v])/ui', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
     foreach($parts as &$part){
      if(!str_starts_with($part, "§")) $part = preg_replace_callback(self::$patterns[$lang], fn(array $match):string => $words[$match[0]], $part);
     }
     unset($part);
     return implode("", $parts);
    }

    public static function marker(string $lang):string{
     return $lang === "ind" ? self::IND_MARKER : "";
    }
}
