<?php

namespace SoulWell\ZulfahmiFjr\event;

use pocketmine\event\Listener;

use SoulWell\ZulfahmiFjr\Main;
use Ifera\ScoreHud\event\TagsResolveEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;

class SoulKeyProvider implements Listener{

    private Main $pl;

    public function __construct(Main $pl){
        $this->pl = $pl;
    }

    public function onTagResolve(TagsResolveEvent $e){
        $tag = $e->getTag();
		$tags = explode('.', $tag->getName(), 2);
		$key = "";
        $p = $e->getPlayer();
		if($tags[0] !== 'soulwell' || count($tags) < 2) return;
        if($tags[1] === "soulkey"){
            $soul = $this->pl->souls->get(strtolower($p->getName()), 0);
            $newTag = new ScoreTag("soulwell.soulkey", (string) $soul);
            $e->setTag($newTag);
        }
    }
}
