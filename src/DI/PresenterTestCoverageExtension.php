<?php

namespace ADT\PresenterTestCoverage\DI;

use ADT\PresenterTestCoverage\Console\CheckUrlCommand;
use ADT\PresenterTestCoverage\Service;

class PresenterTestCoverageExtension extends \Nette\DI\CompilerExtension {

	public function loadConfiguration() {
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig([
			"appNamespacePrefix" => "App",
			"crawlerNamespacePrefix" => "Url",
		]);

		// command pro kontrolu URL tříd
		$builder->addDefinition($this->prefix('command'))
			->setClass(CheckUrlCommand::class)
			->addSetup("setConfig", [$config])
			->setInject(FALSE)
			->addTag('kdyby.console.command');

		$builder->addDefinition($this->prefix('service'))
			->setClass(Service::class)
			->addSetup("setConfig", [$config])
			->setInject(FALSE);
	}

}
