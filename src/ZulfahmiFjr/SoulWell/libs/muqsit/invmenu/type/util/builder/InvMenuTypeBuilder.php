<?php

declare(strict_types=1);

namespace ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\type\util\builder;

use ZulfahmiFjr\SoulWell\libs\muqsit\invmenu\type\InvMenuType;

interface InvMenuTypeBuilder{

	public function build() : InvMenuType;
}