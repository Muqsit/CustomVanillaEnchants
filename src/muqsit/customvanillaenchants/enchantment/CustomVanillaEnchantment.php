<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants\enchantment;

use muqsit\customvanillaenchants\CustomEnchantmentInfo;

interface CustomVanillaEnchantment{

	public function loadConfiguration(CustomEnchantmentInfo $info) : void;
}