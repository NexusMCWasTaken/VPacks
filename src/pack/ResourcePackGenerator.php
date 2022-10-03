<?php

namespace vezdehod\packs\pack;

use GlobalLogger;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\Server;
use pocketmine\utils\Config;
use Ramsey\Uuid\Uuid;
use vezdehod\packs\utils\JsonSerializer;
use vezdehod\packs\VPacksAPI;
use vezdehod\packs\VPacksMain;
use ZipArchive;
use function md5_file;
use function unlink;

class ResourcePackGenerator implements IPackSupplier {

    private const UUID_PACK_NAMESPACE = '010bcfd7-ab47-47c2-bc5b-82bbbd809906';
    private const UUID_RESOURCE_NAMESPACE = 'ae26160e-ced6-4467-9a91-a52e6172dc0b';

    private ZipArchive $archive;
    private string $checksumSource = '';

    public function __construct(
        private string $path,
        private string $name,
		private ?string $description = null,
        private ?string $uuid = null,
        private ?string $resourceUuid = null
    ) {
        @unlink($this->path);
        $this->archive = new ZipArchive();
        $this->archive->open($this->path, ZipArchive::CREATE);
    }

	/**
	 * Function getName
	 * @return string
	 */
	public function getName(): string{
		return $this->name;
	}

	/**
	 * Function getPath
	 * @return string
	 */
	public function getPath(): string{
		return $this->path;
	}

	/**
	 * Function getUuid
	 * @return null|string
	 */
	public function getUuid(): ?string{
		return $this->uuid;
	}

	/**
	 * Function getResourceUuid
	 * @return null|string
	 */
	public function getResourceUuid(): ?string{
		return $this->resourceUuid;
	}

    public function addFile(string $inPack, string $path): void {
        $this->archive->addFile($path, $inPack);
        $this->checksumSource .= md5_file($inPack);
    }

    public function addFromString(string $inPack, string $content): void {
        $this->archive->addFromString($inPack, $content);
        $this->checksumSource .= $content;
    }

    public function generate(): ResourcePack {
        $this->injectManifest();
        $this->archive->close();
        return new ZippedResourcePack($this->path);
    }

    private function injectManifest(): void {
		$vpacks = Server::getInstance()->getPluginManager()->getPlugin("VPacks");
		if (!$vpacks instanceof VPacksMain) $vpacks = $vpacksDataFolder = null;
		else {
			$vpacksDataFolder = $vpacks->getDataFolder();
		}
		$apiDataFolder = null;
		try {
			$VPacksAPI = VPacksAPI::getInstance();
			$apiDataFolder = $VPacksAPI->getPlugin()->getDataFolder();
		} catch (\Throwable $throwable) {
			GlobalLogger::get()->logException($throwable);
		}
		if (($vpacksDataFolder == null || !isset($vpacksDataFolder)) && ($apiDataFolder == null || !isset($apiDataFolder))) throw new \ErrorException("Please use VPacksAPI or phar");
        $this->addFromString("manifest.json", JsonSerializer::serialize([
            'format_version' => 2,
            'header' => [
                'name' => $this->name,
				'uuid' => ($this->uuid = ($this->uuid ?? Uuid::uuid3(self::UUID_PACK_NAMESPACE, $this->checksumSource)->toString())),
                'description' => $this->description,
                'version' => VPacksAPI::updatePackVersion(new Config((is_null($vpacks) ? $apiDataFolder : $vpacksDataFolder) . ".pack_version.txt", Config::JSON, [0,0,0])),
                'min_engine_version' => [1, 16, 0],
                'author' => ''
            ],
            'modules' => [
                [
                    'type' => 'resources',
                    'uuid' => ($this->resourceUuid = ($this->resourceUuid ?? Uuid::uuid3(self::UUID_RESOURCE_NAMESPACE, $this->checksumSource)->toString())),
                    'version' => [1, 0, 0]
                ]
            ],
			"metadata" => [
				"authors" => [
					"xxAROX",
					"BauboLP",
					"its-vezdehod"
				],
        		"url" => "https://github.com/Cosmetic-X"
			]
        ]));
    }
}