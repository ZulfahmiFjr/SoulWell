<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\session\network\handler;

use Closure;
use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\session\network\NetworkStackLatencyEntry;

interface PlayerNetworkHandler{

	public function createNetworkStackLatencyEntry(Closure $then) : NetworkStackLatencyEntry;
}