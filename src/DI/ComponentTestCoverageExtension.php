<?php

namespace ADT\PresenterTestCoverage\DI;

use ADT\PresenterTestCoverage\Console\CheckUrlCommand;
use ADT\PresenterTestCoverage\Service;

class ComponentTestCoverageExtension extends \Nette\DI\CompilerExtension
{
	public function loadConfiguration(): void
	{

		$config = $this->getConfig();

		$builder = $this->getContainerBuilder();

		// command pro kontrolu URL tříd
		$builder->addDefinition($this->prefix('command'))
			->setClass(CheckUrlCommand::class)
			->addSetup('setConfig', [$config])
			->addTag('kdyby.console.command');

		$builder->addDefinition($this->prefix('service'))
			->setClass(Service::class)
			->addSetup('setConfig', [$config]);
	}
}