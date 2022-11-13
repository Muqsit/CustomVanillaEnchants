<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants\enchantment;

use pocketmine\entity\Entity;
use pocketmine\item\enchantment\MeleeWeaponEnchantment;

abstract class DelegateMeleeWeaponEnchantment extends MeleeWeaponEnchantment{

	protected function __construct(
		private MeleeWeaponEnchantment $inner
	){
		parent::__construct($this->inner->getName(), $this->inner->getRarity(), $this->inner->getPrimaryItemFlags(), $this->inner->getSecondaryItemFlags(), $this->inner->getMaxLevel());
	}

	public function isApplicableTo(Entity $victim) : bool{
		return $this->inner->isApplicableTo($victim);
	}

	public function getDamageBonus(int $enchantmentLevel) : float{
		return $this->inner->getDamageBonus($enchantmentLevel);
	}

	public function onPostAttack(Entity $attacker, Entity $victim, int $enchantmentLevel) : void{
		$this->inner->onPostAttack($attacker, $victim, $enchantmentLevel);
	}
}