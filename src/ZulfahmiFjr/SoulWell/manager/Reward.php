<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

final class Reward{

    public const COLORS = ["common" => "§f", "rare" => "§b", "epic" => "§d", "legendary" => "§6"];

    public function __construct(private Item $item, private string $rarity){
    }

    public function getItem():Item{
     return clone $this->item;
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

    public function getLabel():string{
     return ucfirst($this->rarity);
    }

    public function getName():string{
     return TextFormat::clean($this->item->getName());
    }
}
