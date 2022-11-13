<?php

declare(strict_types=1);

namespace muqsit\customvanillaenchants;

use InvalidArgumentException;
use JsonException;
use muqsit\arithmexp\expression\Expression;
use muqsit\arithmexp\ParseException;
use muqsit\arithmexp\Parser;
use muqsit\customvanillaenchants\enchantment\CustomVanillaEnchantment;
use muqsit\customvanillaenchants\enchantment\FireAspectEnchantment;
use muqsit\customvanillaenchants\enchantment\KnockbackEnchantment;
use muqsit\customvanillaenchants\enchantment\ProtectionEnchantment;
use muqsit\customvanillaenchants\enchantment\SharpnessEnchantment;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use RuntimeException;
use function array_keys;
use function array_map;
use function array_slice;
use function count;
use function fclose;
use function get_debug_type;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function stream_get_contents;

final class Main extends PluginBase{

	private Parser $parser;

	/** @var array<string, CustomEnchantmentInfo> */
	private array $defaults;

	/** @var array<string, CustomEnchantmentInfo> */
	private array $overrides = [];

	protected function onEnable() : void{
		$this->parser = Parser::createDefault();
		$this->defaults = $this->loadDefaultConfiguration();
		$this->overrideDefaultEnchantments();
		$this->loadOverrides();
	}

	private function loadOverrides() : void{
		$overrides = $this->getConfig()->get("overrides", []);
		is_array($overrides) || throw new RuntimeException("Expeceted overrides to be an array, got " . get_debug_type($overrides));
		foreach($overrides as $identifier => $config){
			is_string($identifier) || throw new RuntimeException("Expected \"overrides\" entry identifier to be a string, got " . get_debug_type($identifier));
			is_array($config) || throw new RuntimeException("Expected \"overrides\" entry value to be an array, got " . get_debug_type($config));
			foreach($config as $config_identifier => $config_value){
				is_string($config_identifier) || throw new RuntimeException("Expected \"overrides\" config entry identifier to be a string, got " . get_debug_type($config_identifier));
				is_string($config_value) || throw new RuntimeException("Expected \"overrides\" config entry identifier to be a string, got " . get_debug_type($config_value));
				$this->setCustomVanillaEnchantmentConfig($identifier, $config_identifier, $this->parser->parse($config_value), false);
			}
		}
	}

	/**
	 * @return array<string, CustomEnchantmentInfo>
	 */
	private function loadDefaultConfiguration() : array{
		$resources = $this->getResource("configurables.json") ?? throw new RuntimeException("Failed to load configurable resources");
		$configurables_json = stream_get_contents($resources);
		fclose($resources);

		try{
			$configurables = json_decode($configurables_json, true, 512, JSON_THROW_ON_ERROR);
		}catch(JsonException $e){
			throw new RuntimeException("Failed to parse configurable resources: {$e->getMessage()}", 0, $e);
		}

		is_array($configurables) || throw new RuntimeException("Expected configurable resources to be an array, got " . get_debug_type($configurables));

		$configurables["base"] ?? throw new InvalidArgumentException("No \"base\" was specified in configuration");
		$configurables["base"]["variables"] ?? throw new InvalidArgumentException("No \"variables\" was specified in \"base\" configuration");
		is_array($configurables["base"]["variables"]) ?? throw new InvalidArgumentException("Expected \"base\" \"variables\" to be an array, got " . get_debug_type($configurables["base"]["variables"]));

		$base_variables = [];
		foreach($configurables["base"]["variables"] as $variable){
			is_string($variable) ?? throw new InvalidArgumentException("Expected \"base\" \"variables\" entry to be a string, got " . get_debug_type($variable));
			$base_variables[] = $variable;
		}

		$configurables["enchantments"] ?? throw new InvalidArgumentException("No \"enchantments\" was specified in configuration");
		is_array($configurables["enchantments"]) ?? throw new InvalidArgumentException("Expected \"enchantments\" to be an array, got " . get_debug_type($configurables["enchantments"]));

		$result = [];
		foreach($configurables["enchantments"] as $identifier => $data){
			is_string($identifier) || throw new RuntimeException("Expected \"enchantments\" entry identifier to be a string, got " . get_debug_type($identifier));
			is_array($data) || throw new RuntimeException("Expected \"enchantments\" entry value to be an array, got " . get_debug_type($data));

			try{
				$info = CustomEnchantmentInfo::fromData($this->parser, $data, $base_variables);
			}catch(InvalidArgumentException $e){
				throw new RuntimeException("Failed to parse entry \"{$identifier}\"", 0, $e);
			}

			$result[$identifier] = $info;
		}
		return $result;
	}

	private function getCustomVanillaEnchantmentInfo(string $identifier) : CustomEnchantmentInfo{
		$info = clone $this->defaults[$identifier];
		$info->config = array_merge($this->defaults[$identifier]->config, $this->overrides[$identifier]?->config ?? []);
		return $info;
	}

	private function getCustomVanillaEnchantmentConfig(string $identifier, string $configuration) : Expression{
		return $this->overrides[$identifier]?->config[$configuration] ??
			$this->defaults[$identifier]?->config[$configuration] ??
			throw new InvalidArgumentException("Configuration entry {$identifier}->{$configuration} does not exist");
	}

	private function setCustomVanillaEnchantmentConfig(string $identifier, string $configuration, Expression $expression, bool $save = true) : void{
		if(!isset($this->defaults[$identifier], $this->defaults[$identifier]->config[$configuration])){
			throw new InvalidArgumentException("Configuration entry {$identifier}->{$configuration} does not exist");
		}

		$this->defaults[$identifier]->validateExpression($expression);

		if(!isset($this->overrides[$identifier])){
			$this->overrides[$identifier] = clone $this->defaults[$identifier];
			$this->overrides[$identifier]->config = [];
		}

		$this->overrides[$identifier]->config[$configuration] = $expression;

		foreach($this->overrides[$identifier]->config as $config_identifier => $config_value){
			if($this->defaults[$identifier]->config[$config_identifier]->getExpression() === $config_value->getExpression()){
				unset($this->overrides[$identifier]->config[$config_identifier]);
			}
		}

		if(count($this->overrides[$identifier]->config) === 0){
			unset($this->overrides[$identifier]);
		}

		if($save){
			$config = $this->getConfig();
			$config->set("overrides", array_map(static fn(CustomEnchantmentInfo $info) : array => array_map(
				static fn(Expression $expression) : string => $expression->getExpression(),
				$info->config
			), $this->overrides));
			$config->save();
		}

		$this->onUpdateCustomVanillaEnchantmentConfig($identifier);
	}

	private function onUpdateCustomVanillaEnchantmentConfig(string $identifier) : void{
		$info = $this->getCustomVanillaEnchantmentInfo($identifier);
		$parent = StringToEnchantmentParser::getInstance()->parse($info->enchantment_id) ?? throw new RuntimeException("Cannot resolve enchantment \"{$info->enchantment_id}\"");
		if(!($parent instanceof CustomVanillaEnchantment)){
			throw new RuntimeException("Expected \"{$info->enchantment_id}\" to be an instance of " . CustomVanillaEnchantment::class . ", got " . $parent::class);
		}
		$parent->loadConfiguration($info);
	}

	private function overrideDefaultEnchantments() : void{
		$map = EnchantmentIdMap::getInstance();
		$parser = StringToEnchantmentParser::getInstance();
		foreach(array_keys($this->defaults) as $identifier){
			$info = $this->getCustomVanillaEnchantmentInfo($identifier);
			$old_enchantment = $parser->parse($info->enchantment_id) ?? throw new RuntimeException("Cannot resolve enchantment \"{$info->enchantment_id}\"");
			/** @var Enchantment&CustomVanillaEnchantment $new_enchantment */
			$new_enchantment = match($info->enchantment_id){
				"blast_protection" => new ProtectionEnchantment($old_enchantment),
				"feather_falling" => new ProtectionEnchantment($old_enchantment),
				"fire_aspect" => new FireAspectEnchantment($old_enchantment),
				"fire_protection" => new ProtectionEnchantment($old_enchantment),
				"knockback" => new KnockbackEnchantment($old_enchantment),
				"projectile_protection" => new ProtectionEnchantment($old_enchantment),
				"protection" => new ProtectionEnchantment($old_enchantment),
				"sharpness" => new SharpnessEnchantment($old_enchantment),
				default => throw new RuntimeException("No mapping found for enchantment \"{$info->enchantment_id}\"")
			};
			$new_enchantment->loadConfiguration($info);
			$map->register($map->toId($old_enchantment), $new_enchantment);
			$parser->override($info->enchantment_id, fn() => $new_enchantment);
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$args_c = count($args);
		if($args_c > 0){
			if($args[0] === "set"){
				if($args_c < 4){
					$sender->sendMessage(
						TextFormat::RED . "/{$label} {$args[0]} <enchantment> <config> <value>" . TextFormat::EOL .
						TextFormat::RED . "Available Enchantments: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($this->defaults))
					);
					return true;
				}

				$enchantment_identifier = $args[1];
				if(!isset($this->defaults[$enchantment_identifier])){
					$sender->sendMessage(
						TextFormat::RED . "Enchantment \"{$enchantment_identifier}\" is not registered." . TextFormat::EOL .
						TextFormat::RED . "Available Enchantments: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($this->defaults))
					);
					return true;
				}

				$enchantment = $this->defaults[$enchantment_identifier];
				$config = $args[2];
				if(!isset($enchantment->config[$config])){
					$sender->sendMessage(
						TextFormat::RED . "Enchantment \"{$enchantment_identifier}\" does not have the configuration entry \"{$config}\"." . TextFormat::EOL .
						TextFormat::RED . "Available Enchantment Configuration Entries: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($enchantment->config))
					);
					return true;
				}

				$previous = $this->getCustomVanillaEnchantmentConfig($enchantment_identifier, $config)->getExpression();

				$expression_string = implode(" ", array_slice($args, 3));
				try{
					$expression = $this->parser->parse($expression_string);
				}catch(ParseException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
					return true;
				}

				try{
					$this->setCustomVanillaEnchantmentConfig($enchantment_identifier, $config, $expression);
				}catch(InvalidArgumentException $e){
					$sender->sendMessage(TextFormat::RED . $e->getMessage());
					return true;
				}

				$sender->sendMessage(
					TextFormat::GREEN . "Updated configuration for {$enchantment_identifier}->{$config}" . TextFormat::EOL .
					TextFormat::GREEN . "Outdated Value: " . TextFormat::GRAY . $previous . TextFormat::EOL .
					TextFormat::GREEN . "Updated Value: " . TextFormat::GRAY . $expression->getExpression()
				);
				return true;
			}

			if($args[0] === "list"){
				if($args_c !== 2){
					$sender->sendMessage(
						TextFormat::RED . "/{$label} {$args[0]} <enchantment>" . TextFormat::EOL .
						TextFormat::RED . "Available Enchantments: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($this->defaults))
					);
					return true;
				}

				$enchantment_identifier = $args[1];
				if(!isset($this->defaults[$enchantment_identifier])){
					$sender->sendMessage(
						TextFormat::RED . "Enchantment \"{$enchantment_identifier}\" is not registered." . TextFormat::EOL .
						TextFormat::RED . "Available Enchantments: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($this->defaults))
					);
					return true;
				}

				$enchantment = $this->defaults[$enchantment_identifier];
				$message = TextFormat::RED . "Enchantment (\"{$enchantment_identifier}\") Configuration" . TextFormat::EOL;
				foreach(array_keys($enchantment->config) as $identifier){
					$message .= TextFormat::RED . "{$identifier}: " . TextFormat::GRAY . $this->getCustomVanillaEnchantmentConfig($enchantment_identifier, $identifier)->getExpression() . TextFormat::EOL;
				}
				$sender->sendMessage(rtrim($message, TextFormat::EOL));
				return true;
			}

			if($args[0] === "reset"){
				if($args_c !== 3){
					$sender->sendMessage(
						TextFormat::RED . "/{$label} {$args[0]} <enchantment> <config>" . TextFormat::EOL .
						TextFormat::RED . "Available Enchantments: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($this->defaults))
					);
					return true;
				}

				$enchantment_identifier = $args[1];
				if(!isset($this->defaults[$enchantment_identifier])){
					$sender->sendMessage(
						TextFormat::RED . "Enchantment \"{$enchantment_identifier}\" is not registered." . TextFormat::EOL .
						TextFormat::RED . "Available Enchantments: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($this->defaults))
					);
					return true;
				}

				$enchantment = $this->defaults[$enchantment_identifier];
				$config = $args[2];
				if(!isset($enchantment->config[$config])){
					$sender->sendMessage(
						TextFormat::RED . "Enchantment \"{$enchantment_identifier}\" does not have the configuration entry \"{$config}\"." . TextFormat::EOL .
						TextFormat::RED . "Available Enchantment Configuration Entries: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($enchantment->config))
					);
					return true;
				}

				$previous = $this->getCustomVanillaEnchantmentConfig($enchantment_identifier, $config)->getExpression();
				$this->setCustomVanillaEnchantmentConfig($enchantment_identifier, $config, $enchantment->config[$config]);
				$updated = $this->getCustomVanillaEnchantmentConfig($enchantment_identifier, $config)->getExpression();
				$sender->sendMessage(
					TextFormat::GREEN . "Reset configuration for {$enchantment_identifier}->{$config}" . TextFormat::EOL .
					TextFormat::GREEN . "Outdated Value: " . TextFormat::GRAY . $previous . TextFormat::EOL .
					TextFormat::GREEN . "Updated Value: " . TextFormat::GRAY . $updated
				);
				return true;
			}
		}

		$sender->sendMessage(
			TextFormat::BOLD . TextFormat::RED . "Custom Vanilla Enchantments" . TextFormat::RESET . TextFormat::EOL .
			TextFormat::RED . "/{$label} list <enchantment>" . TextFormat::GRAY . " - List an enchantment's configuration" . TextFormat::EOL .
			TextFormat::RED . "/{$label} set <enchantment> <config> <value>" . TextFormat::GRAY . " - Set an enchantment's configuration" . TextFormat::EOL .
			TextFormat::RED . "/{$label} reset <enchantment> <config>" . TextFormat::GRAY . " - Reset an enchantment's configuration" . TextFormat::EOL .
			TextFormat::RED . "Available Enchantments: " . implode(TextFormat::GRAY . ", " . TextFormat::RED, array_keys($this->defaults))
		);
		return true;
	}
}