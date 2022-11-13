<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants\enchantment;

use muqsit\arithmexp\expression\Expression;
use muqsit\customvanillaenchants\CustomEnchantmentInfo;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\item\enchantment\KnockbackEnchantment as VanillaKnockbackEnchantment;

final class KnockbackEnchantment extends VanillaKnockbackEnchantment implements CustomVanillaEnchantment{

	private Expression $damage_bonus;
	private Expression $force;

	public function __construct(VanillaKnockbackEnchantment $inner){
		parent::__construct($inner->getName(), $inner->getRarity(), $inner->getPrimaryItemFlags(), $inner->getSecondaryItemFlags(), $inner->getMaxLevel());
	}

	public function loadConfiguration(CustomEnchantmentInfo $info) : void{
		$this->damage_bonus = $info->config["damage_bonus"];
		$this->force = $info->config["force"];
	}

	public function getDamageBonus(int $enchantmentLevel) : float{
		return $this->damage_bonus->evaluate(["level" => $enchantmentLevel]);
	}

	public function onPostAttack(Entity $attacker, Entity $victim, int $enchantmentLevel) : void{
		if($victim instanceof Living){
			$diff = $victim->getPosition()->subtractVector($attacker->getPosition());
			$victim->knockBack($diff->x, $diff->z, $this->force->evaluate(["level" => $enchantmentLevel]));
		}
	}
}