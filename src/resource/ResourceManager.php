<?php

namespace vezdehod\packs\resource;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use vezdehod\packs\IPackManager;
use vezdehod\packs\pack\IPackSupplier;
use vezdehod\packs\PluginContent;


class ResourceManager implements IPackManager {
	public function fromPlugin(Plugin $plugin): PluginResources {
        return new PluginResources($plugin);
    }

	/**
	 * Function inject
	 * @param IPackSupplier $supplier
	 * @param array<PluginContent> $contents
	 * @return void
	 */
    public function inject(IPackSupplier $supplier, array $contents): void {
        foreach ($contents as $content) {
            foreach ($content->getResources()->getAll() as $resource) {
                $supplier->addFile($resource->getInPackPath(), $resource->getPath());
            }
        }
    }

}