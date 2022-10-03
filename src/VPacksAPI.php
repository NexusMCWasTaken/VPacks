<?php
declare(strict_types=1);
namespace vezdehod\packs;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use vezdehod\packs\pack\ResourcePackGenerator;
use vezdehod\packs\resource\ResourceManager;
use vezdehod\packs\ui\UIManager;


/**
 * Class VPacksAPI
 * @package vezdehod\packs
 * @author Jan Sohn / xxAROX
 * @date 03. October, 2022 - 10:24
 * @ide PhpStorm
 * @project VPacks
 */
class VPacksAPI{
	use SingletonTrait{
		setInstance as private;
		reset as private;
	}
	private UIManager $uiManager;
	private ResourceManager $resourceManager;

	/**
	 * VPacksAPI constructor.
	 */
	public function __construct(
		private PluginBase $plugin,
		private string $name,
		private string $description,
		private string $uuid,
		private string $resourceUuid
	){
		self::setInstance($this);
		$this->uiManager = new UIManager();
		$this->resourceManager = new ResourceManager();

		ContentFactory::setFactory(fn (Plugin $plugin) => new PluginContent($plugin, $this->uiManager->fromPlugin($plugin), $this->resourceManager->fromPlugin($plugin),));
		$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(\Closure::bind(function (): void{$this->enable();}, $this, $this)), 60);
	}

	private function enable(): void{
		$this->plugin->getLogger()->info("Making pack..");
		$contents = ContentFactory::getAndLock();

		//This is need to be sorted, because of PMMP random plugin loading to avoid alphabetical order depending
		//https://github.com/pmmp/PocketMine-MP/blob/2b61c025c26394c3293bcc12a2d0b2301cb4c7ee/src/plugin/PluginManager.php#L237
		ksort($contents);

		$generator = new ResourcePackGenerator(
			$this->plugin->getDataFolder() . $this->plugin->getName() . ".zip",
			$this->name,
			$this->description,
			$this->uuid,
			$this->resourceUuid
		);

		$this->resourceManager->inject($generator, $contents);
		$this->uiManager->inject($generator, $contents);


		$pack = $generator->generate();

		$manager = $this->plugin->getServer()->getResourcePackManager();
		$reflection = new \ReflectionClass($manager);

		$packsProperty = $reflection->getProperty("resourcePacks");
		$packsProperty->setAccessible(true);
		$currentResourcePacks = $packsProperty->getValue($manager);

		$uuidProperty = $reflection->getProperty("uuidList");
		$uuidProperty->setAccessible(true);
		$currentUUIDPacks = $uuidProperty->getValue($manager);

		$property = $reflection->getProperty("serverForceResources");
		$property->setAccessible(true);
		$property->setValue($manager, true);

		$currentUUIDPacks[strtolower($pack->getPackId())] = $currentResourcePacks[] = $pack;

		$packsProperty->setValue($manager, $currentResourcePacks);
		$uuidProperty->setValue($manager, $currentUUIDPacks);
		$this->plugin->getLogger()->info("Pack injected!");
	}

	/**
	 * Function getPlugin
	 * @return PluginBase
	 */
	public function getPlugin(): PluginBase{
		return $this->plugin;
	}

	/**
	 * Function getPackVersion
	 * @param Config $config
	 * @return array<int, int, int>
	 */
	public static function getPackVersion(Config $config): array{
		return $config->get("pack-version", [0,0,0]);
	}

	/**
	 * Function updatePackVersion
	 * @param Config $config
	 * @return array<int, int, int>
	 */
	public static function updatePackVersion(Config $config): array{
		$currentVersionArray = $config->get("pack-version", [0,0,0]);
		$currentVersionArray[2]++;
		$config->set("pack-version", $currentVersionArray);
		$config->save();
		return $currentVersionArray;
	}
}
