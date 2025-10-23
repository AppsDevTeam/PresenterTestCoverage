<?php

namespace ADT\PresenterTestCoverage\Console;

use ADT\PresenterTestCoverage\Service;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'adt:component-test-coverage', description: 'Writes hello world.')]
class CheckUrlCommand extends Command
{
	protected array $config = [];
	protected Service $service;
	protected static $defaultName = 'adt:component-test-coverage';
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
		$this->setName('adt:component-test-coverage');
		$this->setDescription('Najde všechny presentery a testy na presentery. Vypíše, které metody (action, render a handle) jsou otestované a které ne.');
		$this->addOption("psr4");
		$this->addOption("missing-tests", "MT");

	}

	protected function initialize(InputInterface $input, OutputInterface $output): void
	{
		$output->getFormatter()->setStyle('danger', new OutputFormatterStyle('red'));
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$this->service->getRobotLoader()->rebuild();
		}
		catch(\ADT\PresenterTestCoverage\ComponentCoverageException $e){
			$output->writeln("<danger>----------<danger>");
			$output->writeln("<danger>" .$e->getMessage() . "</danger>\n" );
			return Command::FAILURE;
		}


		$this->service->getFoundMethods();

		if($input->getOption("psr4") || $input->getOption("missing-tests")) {

			if($input->getOption("psr4")) {
				$wrongPSRCount = count($this->service->getPSR4Incompatible());
				if($wrongPSRCount){
					$output->writeln("----------");
					$output->writeln("Soubory neplnící PSR-4 konvenci: ");
					foreach ($this->service->getPSR4Incompatible() as $incompatible) {
						$output->writeln("<danger>" . $incompatible . "</danger>" );
					}
					return Command::FAILURE;
				}
				return Command::SUCCESS;
			}


			if($input->getOption("missing-tests")) {
				$missingTestCount = count($this->service->getMissingMethods());

				if($missingTestCount){
					$output->writeln("----------");
					$output->writeln("Chybějící testy: ");
					foreach ($this->service->getMissingMethods() as $_missingMethod) {
						$output->writeln("<danger>" . $_missingMethod . "</danger>" );
					}
					return Command::FAILURE;
				}

				return Command::SUCCESS;
			}

		}


		$return = Command::SUCCESS;
		$output->writeln("----------");
		$output->writeln("Nalezené testy: ");
		foreach ($this->service->getFoundMethods() as $_missingMethod) {
			$output->writeln("<info>" . $_missingMethod . "</info>");
		}

		$output->writeln("----------");
		$output->writeln("Chybějící testy: ");
		foreach ($this->service->getMissingMethods() as $_missingMethod) {
			$output->writeln("<danger>" . $_missingMethod . "</danger>" );
			$return = Command::FAILURE;
		}


		$notPSR = $this->service->getPSR4Incompatible();
		if(!empty($notPSR)){
			$output->writeln("----------");
			$output->writeln("Soubory neplnící PSR-4 konvenci: ");
			foreach ($notPSR as $incompatible) {
				$output->writeln("<danger>" . $incompatible . "</danger>" );
				$return = Command::FAILURE;
			}
		}

		return $return;
	}
}
