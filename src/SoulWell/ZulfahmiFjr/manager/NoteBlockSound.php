<?php

namespace SoulWell\ZulfahmiFjr\manager;

use pocketmine\level\sound\Sound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class NoteBlockSound extends Sound{

    public function __construct(Vector3 $pos, $note){
     parent::__construct($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
     $this->note = $note;
    }

    public function encode(){
     $pk = new BlockEventPacket();
     $pk->x = $this->x;
     $pk->y = $this->y;
     $pk->z = $this->z;
     $pk->eventType = 0;
     $pk->eventData = $this->note;
     $pk2 = new LevelSoundEventPacket();
     $pk2->sound = LevelSoundEventPacket::SOUND_NOTE;
     $pk2->position = $this;
     $pk2->extraData = 0 | $this->note;
     return [$pk, $pk2];
    }

}
