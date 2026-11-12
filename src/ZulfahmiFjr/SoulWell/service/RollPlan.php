<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\service;

use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\DyeColor;
use ZulfahmiFjr\SoulWell\manager\RewardManager;

final class RollPlan{

    public array $predictions = [];
    public array $frames = [];
    public array $delays = [];
    public array $notes = [];
    public array $finished = [];

    public function __construct(RewardManager $rewards, int $rolls, bool $custom){
     $data = NativeRoll::plan($rewards->getNativeConfig(), $rolls, $custom);
     $catalog = $rewards->getCatalog();
     $columns = $rolls === 5 ? [0, 2, 4, 6, 8] : ($rolls === 3 ? [2, 4, 6] : [4]);
     foreach($columns as $i => $column) $this->predictions[$column] = $catalog[$data[$i]]["reward"] ?? throw new \RuntimeException("Invalid native winner index");
     $items = [-1 => VanillaBlocks::AIR()->asItem()];
     foreach($catalog as $i => $entry) $items[$i] = $entry["reward"]->getItem();
     if(!$custom){
      $items[-2] = VanillaBlocks::END_ROD()->asItem();
      foreach(array_values(DyeColor::getAll()) as $i => $color) $items[-3 - $i] = VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem();
     }
     for($frame = 0; $frame < 40; $frame++){
      $offset = $frame === 39 ? 2189 : 7 + $frame * 56;
      $contents = [];
      for($slot = 0; $slot < 54; $slot++){
       $id = $data[$offset + $slot];
       if($id !== -19) $contents[$slot] = $items[$id] ?? throw new \RuntimeException("Invalid native frame index");
      }
      if($frame === 39){$this->finished = $contents;continue;}
      $this->frames[$frame] = $contents;
      $this->delays[$frame] = $data[$offset - 2];
      $this->notes[$frame] = $data[$offset - 1];
     }
    }
}
