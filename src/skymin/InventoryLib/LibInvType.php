<?php
/**
 *      _                    _       
 *  ___| | ___   _ _ __ ___ (_)_ __  
 * / __| |/ / | | | '_ ` _ \| | '_ \ 
 * \__ \   <| |_| | | | | | | | | | |
 * |___/_|\_\\__, |_| |_| |_|_|_| |_|
 *           |___/ 
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 * 
 * @author skymin
 * @link   https://github.com/sky-min
 * @license https://opensource.org/licenses/MIT MIT License
 * 
 *   /\___/\
 * 　(∩`・ω・)
 * ＿/_ミつ/￣￣￣/
 * 　　＼/＿＿＿/
 *
 */

declare(strict_types = 1);

namespace skymin\InventoryLib;

use pocketmine\utils\EnumTrait;
use pocketmine\block\BlockLegacyIds;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;


/**
 * @method static self CHEST()
 * @method static self DOUBLE_CHEST()
 * @method static self DROPPER()
 * @method static self HOPPER()
 */
final class LibInvType{
	use EnumTrait;
	
	protected static function setup() : void{
		self::registerAll(
			new self('chest'),
			new self('double_chest'),
			new self('dropper'),
			new self('hopper')
		);
	}
	
	public function isDouble() : bool{
		return ($this->id() === self::DOUBLE_CHEST()->id());
	}
	
	public function getWindowType() : int{
		if($this->id() === self::CHEST()->id() || $this->id() === self::DOUBLE_CHEST()->id()){
			return WindowTypes::CONTAINER;
		}else if($this->id() === self::DROPPER()->id()){
			return WindowTypes::DROPPER; 
		}else if($this->id() === self::HOPPER()->id()){
			return WindowTypes::HOPPER;
		}
		return 0;
	}
	
	public function getSize() : int{
		if($this->id() === self::CHEST()->id()){
			return 27;
		}else if($this->id() === self::DOUBLE_CHEST()->id()){
			return 54;
		}else if($this->id() === self::DROPPER()->id()){
			return 9;
		}else if($this->id() === self::HOPPER()->id()){
			return 5;
		}
		return 0;
	}
	
	public function getBlockId() : int{
		if($this->id() === self::CHEST()->id() || $this->id() === self::DOUBLE_CHEST()->id()){
			return BlockLegacyIds::CHEST;
		}else if($this->id() === self::DROPPER()->id()){
			return BlockLegacyIds::DROPPER; 
		}else if($this->id() === self::HOPPER()->id()){
			return BlockLegacyIds::HOPPER_BLOCK;
		}
		return 0;
	}
	
}
