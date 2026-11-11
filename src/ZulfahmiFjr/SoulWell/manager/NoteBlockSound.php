<?php

namespace ZulfahmiFjr\SoulWell\manager;

use pocketmine\world\sound\Sound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

class NoteBlockSound implements Sound{

    private $note;

    public function __construct($note){
     $this->note = $note;
    }

    public function encode(Vector3 $vector):array{
     $pk = new BlockEventPacket();
     $x = (int) round($vector->x);
     $y = (int) round($vector->y);
     $z = (int) round($vector->z);
     $pk->blockPosition = new BlockPosition($x, $y, $z);
     $pk->eventType = 0;
     $pk->eventData = $this->note;
     $pk2 = LevelSoundEventPacket::nonActorSound(LevelSoundEvent::NOTE, $vector, true, $this->note);
     return [$pk, $pk2];
    }

}
