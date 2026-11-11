<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

final class MenuForm implements Form{

    private bool $handled = false;

    public function __construct(private array $data, private \Closure $callback){
    }

    public function jsonSerialize():array{
     return $this->data;
    }

    public function handleResponse(Player $player, $data):void{
     if($this->handled) return;
     $this->handled = true;
     if($data === null) return;
     if(!is_int($data) || !isset($this->data["buttons"][$data])) throw new FormValidationException("Invalid SoulWell menu selection");
     ($this->callback)($player, $data);
    }
}
