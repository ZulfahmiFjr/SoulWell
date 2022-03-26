<?php

namespace SoulWell\ZulfahmiFjr\manager;

use pocketmine\world\sound\Sound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class NoteBlockSound implements Sound{

    private $note;
    
    public function __construct($note){
     $this->note = $note;
    }

    public function encode(Vector3 $vector):array{
     $pk = new BlockEventPacket();
     $pk->blockPosition = new BlockPosition($vector->x, $vector->y, $vector->z);
     $pk->eventType = 0;
     $pk->eventData = $this->note;
     $pk2 = new LevelSoundEventPacket();
     $pk2->sound = 81;
     $pk2->position = $vector;
     $pk2->extraData = 0 | $this->note;
     return [$pk, $pk2];
    }

}
