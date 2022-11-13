<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants\enchantment;

use muqsit\arithmexp\expression\Expression;
use muqsit\customvanillaenchants\CustomEnchantmentInfo;
use pocketmine\item\enchantment\ProtectionEnchantment as VanillaProtectionEnchantment;

final class ProtectionEnchantment extends VanillaProtectionEnchantment implements CustomVanillaEnchantment{

	private Expression $factor;

	public function __construct(VanillaProtectionEnchantment $inner){
		parent::__construct($inner->getName(), $inner->getRarity(), $inner->getPrimaryItemFlags(), $inner->getSecondaryItemFlags(), $inner->getMaxLevel(), $inner->getTypeModifier(), $inner->applicableDamageTypes);
	}

	public function loadConfiguration(CustomEnchantmentInfo $info) : void{
		$this->factor = $info->config["factor"];
	}

	public function getProtectionFactor(int $level) : int{
		return (int) $this->factor->evaluate(["level" => $level]);
	}
}