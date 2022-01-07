<?php

namespace ADT\PresenterTestCoverage\Console;

use ADT\PresenterTestCoverage\Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckUrlCommand extends Command {

	/** @var array */
	protected $config = [];

	/** \Nette\DI\Container */
	protected $container;

	/** @var Service */
	protected $service;

	/**
	 * @param array $config
	 */
	public function setConfig(array $config = []) {
		$this->config = $config;
	}

	protected function configure() {
		$this->setName('adt:presenterTestCoverage');
		$this->setDescription('Najde všechny presentery a testy na presentery. Vypíše, které metody (action, render a handle) jsou otestované a které ne.');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function initialize(InputInterface $input, OutputInterface $output) {
		$output->getFormatter()->setStyle('danger', new OutputFormatterStyle('red'));

		$this->container = $this->getHelper("container")->getByType('Nette\DI\Container');
		$this->service = $this->container->getByType(Service::class);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$loader = new \Nette\Loaders\RobotLoader;
		$loader->addDirectory($this->config["testDir"]);
		$loader->setTempDirectory($this->config["tempDir"]);
		$loader->register();

		$output->writeln("----------");
		$output->writeln("Nalezené testy: ");
		foreach ($this->service->getFoundMethods() as $class) {
			$output->writeln("<info>" . $class . "</info>");
		}

		$output->writeln("----------");
		$output->writeln("Chybějící testy: ");
		foreach ($this->service->getMissingMethods() as $class) {
			$output->writeln("<danger>" . $class . "</danger>" );
		}
	}


}
