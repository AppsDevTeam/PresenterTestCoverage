<?php

namespace ADT\PresenterTestCoverage\Console;

use ADT\PresenterTestCoverage\Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckUrlCommand extends Command
{
	protected array $config = [];
	protected Service $service;
	protected static $defaultName = 'adt:presenterTestCoverage';
	public function __construct(Service $service) {
		parent::__construct();

		$this->service = $service;
	}

	public function setConfig(array $config = []): void
	{
		$this->config = $config;
	}

	protected function configure(): void
	{
		$this->setName('adt:presenterTestCoverage');
		$this->setDescription('Najde všechny presentery a testy na presentery. Vypíše, které metody (action, render a handle) jsou otestované a které ne.');
	}

	protected function initialize(InputInterface $input, OutputInterface $output): void
	{
		$output->getFormatter()->setStyle('danger', new OutputFormatterStyle('red'));
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->service->getRobotLoader()->rebuild();

		$output->writeln("----------");
		$output->writeln("Nalezené testy: ");
		foreach ($this->service->getFoundMethods() as $_missingMethod) {
			$output->writeln("<info>" . $_missingMethod . "</info>");
		}

		$output->writeln("----------");
		$output->writeln("Chybějící testy: ");
		foreach ($this->service->getMissingMethods() as $_missingMethod) {
			$output->writeln("<danger>" . $_missingMethod . "</danger>" );
		}

		$wrongConfig = $this->service->getSkippedForMissingConfiguration();
		if(!empty($wrongConfig)){
			$output->writeln("----------");
			$output->writeln("Chyby v konfiguraci: ");
			foreach ($wrongConfig as $misconfiguredSection) {
				$output->writeln("<danger>" . $misconfiguredSection . "</danger>" );
			}
		}

		return 1;
	}
}
