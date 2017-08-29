<?php

namespace ADT\PresenterTestCoverage\DI;

use ADT\PresenterTestCoverage\Console\PresenterTestCoverageCommand;

class PresenterTestCoverageExtension extends \Nette\DI\CompilerExtension {

	public function loadConfiguration() {
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig([
			"appNamespacePrefix" => "App",
			"testNamespacePrefix" => "Tests",
			"testClassSuffix" => "Test",
			"testMethodPrefix" => "test",
		]);

		$builder->addDefinition($this->prefix('command'))
			->setClass(PresenterTestCoverageCommand::class)
			->addSetup("setConfig", [$config])
			->setInject(FALSE)
			->addTag('kdyby.console.command');
	}

}
