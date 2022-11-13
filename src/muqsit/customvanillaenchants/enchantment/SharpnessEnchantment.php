<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants\enchantment;

use muqsit\arithmexp\expression\Expression;
use muqsit\customvanillaenchants\CustomEnchantmentInfo;
use pocketmine\item\enchantment\SharpnessEnchantment as VanillaSharpnessEnchantment;

final class SharpnessEnchantment extends VanillaSharpnessEnchantment implements CustomVanillaEnchantment{

	private Expression $damage_bonus;

	public function __construct(VanillaSharpnessEnchantment $inner){
		parent::__construct($inner->getName(), $inner->getRarity(), $inner->getPrimaryItemFlags(), $inner->getSecondaryItemFlags(), $inner->getMaxLevel());
	}

	public function loadConfiguration(CustomEnchantmentInfo $info) : void{
		$this->damage_bonus = $info->config["damage_bonus"];
	}

	public function getDamageBonus(int $enchantmentLevel) : float{
		return $this->damage_bonus->evaluate(["level" => $enchantmentLevel]);
	}
}