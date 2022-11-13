<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants\enchantment;

use muqsit\arithmexp\expression\Expression;
use muqsit\customvanillaenchants\CustomEnchantmentInfo;
use pocketmine\entity\Entity;
use pocketmine\item\enchantment\FireAspectEnchantment as VanillaFireAspectEnchantment;

final class FireAspectEnchantment extends VanillaFireAspectEnchantment implements CustomVanillaEnchantment{

	private Expression $damage_bonus;
	private Expression $duration;

	public function __construct(VanillaFireAspectEnchantment $inner){
		parent::__construct($inner->getName(), $inner->getRarity(), $inner->getPrimaryItemFlags(), $inner->getSecondaryItemFlags(), $inner->getMaxLevel());
	}

	public function loadConfiguration(CustomEnchantmentInfo $info) : void{
		$this->damage_bonus = $info->config["damage_bonus"];
		$this->duration = $info->config["duration"];
	}

	public function getDamageBonus(int $enchantmentLevel) : float{
		return $this->damage_bonus->evaluate(["level" => $enchantmentLevel]);
	}

	public function onPostAttack(Entity $attacker, Entity $victim, int $enchantmentLevel) : void{
		$victim->setOnFire((int) $this->duration->evaluate(["level" => $enchantmentLevel]));
	}
}