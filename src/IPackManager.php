<?php

namespace vezdehod\packs;

use vezdehod\packs\pack\IPackSupplier;

interface IPackManager {
	/**
	 * Function inject
	 * @param IPackSupplier $supplier
	 * @param array<PluginContent> $contents
	 * @return void
	 */
    public function inject(IPackSupplier $supplier, array $contents): void;
}