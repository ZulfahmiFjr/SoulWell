<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use ZulfahmiFjr\SoulWell\service\LanguageService;

final class Reward{

    public const COLORS = ["common" => "§f", "rare" => "§b", "epic" => "§d", "legendary" => "§6"];
    private array $localized = [];

    public function __construct(private Item $item, private string $rarity){
    }

    public function getItem(string $lang = "eng"):Item{
     if($lang !== "ind") return clone $this->item;
     if(!isset($this->localized[$lang])){
      $item = clone $this->item;
      $name = LanguageService::literal($lang, $item->getName());
      if($name !== $item->getName()) $item->setCustomName($name);
      $item->setLore(array_map(fn(string $line):string => LanguageService::literal($lang, $line), $item->getLore()));
      $this->localized[$lang] = $item;
     }
     return clone $this->localized[$lang];
    }

    public function getRarity():string{
     return $this->rarity;
    }

    public function getRank():int{
     return array_search($this->rarity, array_keys(self::COLORS), true);
    }

    public function getColor():string{
     return self::COLORS[$this->rarity];
    }

    public function getLabel(string $lang = "eng"):string{
     return LanguageService::text($lang, "rarity.".$this->rarity);
    }

    public function getName(string $lang = "eng"):string{
     return LanguageService::literal($lang, TextFormat::clean($this->item->getName()));
    }
}
