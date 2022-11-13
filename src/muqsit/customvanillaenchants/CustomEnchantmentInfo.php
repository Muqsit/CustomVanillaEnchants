<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants;

use InvalidArgumentException;
use muqsit\arithmexp\expression\Expression;
use muqsit\arithmexp\ParseException;
use muqsit\arithmexp\Parser;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_string;

final class CustomEnchantmentInfo{

	/**
	 * @param Parser $parser
	 * @param array<string, mixed> $data
	 * @param list<string> $base_variables
	 * @return self
	 */
	public static function fromData(Parser $parser, array $data, array $base_variables) : self{
		$data["enchantment_id"] ?? throw new InvalidArgumentException("No \"enchantment_id\" was specified in configuration");
		is_string($data["enchantment_id"]) || throw new InvalidArgumentException("Expected \"enchantment_id\" to be string, got " . get_debug_type($data["enchantment_id"]));
		StringToEnchantmentParser::getInstance()->parse($data["enchantment_id"]) || throw new InvalidArgumentException("Enchantment \"{$data["enchantment_id"]}\" is not registered");


		$data["config"] ?? throw new InvalidArgumentException("No \"config\" was specified in configuration");
		is_array($data["config"]) || throw new InvalidArgumentException("Expected \"config\" to be an array, got " . get_debug_type($data["config"]));

		$config = [];
		foreach($data["config"] as $identifier => $value){
			is_string($identifier) || throw new InvalidArgumentException("Expected \"config\" key to be string, got " . get_debug_type($identifier));
			is_string($value) || throw new InvalidArgumentException("Expected \"config\" value to be string, got " . get_debug_type($value));

			try{
				$expression = $parser->parse($value);
			}catch(ParseException $e){
				throw new InvalidArgumentException("Failed to parse expression configured for \"{$identifier}\"", 0, $e);
			}

			$config[$identifier] = $expression;
		}

		$data["variables"] ?? throw new InvalidArgumentException("No \"variables\" was specified in configuration");
		is_array($data["variables"]) || throw new InvalidArgumentException("Expected \"variables\" to be an array, got " . get_debug_type($data["variables"]));
		$variables = $base_variables;
		foreach($data["variables"] as $variable){
			is_string($variable) || throw new InvalidArgumentException("Expected \"variables\" value to be string, got " . get_debug_type($variable));
			$variables[] = $variable;
		}

		$info = new self($data["enchantment_id"], $config, $variables);
		foreach($info->config as $identifier => $expression){
			try{
				$info->validateExpression($expression);
			}catch(InvalidArgumentException $e){
				throw new InvalidArgumentException("Failed to configure value for \"{$identifier}\"", 0, $e);
			}
		}
		return $info;
	}

	/**
	 * @param string $enchantment_id
	 * @param array<string, Expression> $config
	 * @param list<string> $variables
	 */
	private function __construct(
		public string $enchantment_id,
		public array $config,
		public array $variables
	){}

	public function validateExpression(Expression $expression) : void{
		foreach($expression->findVariables() as $variable){
			if(!in_array($variable, $this->variables, true)){
				throw new InvalidArgumentException("Expression ({$expression->getExpression()}) contains an unknown variable \"{$variable}\"");
			}
		}
	}
}