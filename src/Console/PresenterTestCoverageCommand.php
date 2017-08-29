<?php

namespace ADT\PresenterTestCoverage\Console;

use Nette\Application\IPresenter;
use Nette\Application\UI\PresenterComponentReflection;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PresenterTestCoverageCommand extends Command {

	/** @var array */
	protected $config = [];

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
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		$methods = $this->getPresenterMethods();
		$foundTests = [];
		$missingTests = [];

		foreach ($methods as $method) {
			if (method_exists($method["class"], $method["method"])) {
				$foundTests[] = $method["full"];

			} else {
				$missingTests[] = $method["full"];
			}
		}

		$output->writeln("----------");
		$output->writeln("Nalezené testy: ");
		foreach ($foundTests as $class) {
			$output->writeln("<info>" . $class . "</info>");
		}

		$output->writeln("----------");
		$output->writeln("Chybějící testy: ");
		foreach ($missingTests as $class) {
			$output->writeln("<danger>" . $class . "</danger>" );
		}
	}

	/**
	 * @return array
	 */
	protected function getPresenterMethods() : array {
		$presenters = $this->container->findByType(IPresenter::class);

		foreach ($presenters as $serviceNumber) {

			/** @var PresenterComponentReflection $reflection */
			$presenterReflection = $this->container->getService($serviceNumber)->reflection;

			if (!$this::isTestedClass($presenterReflection->getName())) {
				continue;
			}

			$reflectionMethods = array_filter($presenterReflection->getMethods(), function (\Nette\Reflection\Method $methodReflection) {
				return self::isTestedMethod($methodReflection->getName());
			});

			foreach ($reflectionMethods as $reflectionMenthod) {
				$params = self::parseClassesMethods($reflectionMenthod);
				$methods[$params["original"]] = $params;
			}
		}

		return $methods;
	}

	/**
	 * @param string $fullClassName
	 * @return bool
	 */
	protected function isTestedClass(string $fullClassName) : bool {
		return Strings::startsWith($fullClassName, $this->config["appNamespacePrefix"] . "\\");
	}

	/**
	 * @param string $methodName
	 * @return bool
	 */
	protected static function isTestedMethod(string $methodName) : bool {
		foreach ([
			 "action",
			 "render",
			 "handle",
		 ] as $methodPrefix) {
			if (Strings::startsWith($methodName, $methodPrefix)) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * @param \Nette\Reflection\Method $methodReflection
	 * @return array
	 */
	protected function parseClassesMethods(\Nette\Reflection\Method $methodReflection) : array {
		$params = [];

		$params["original"] = $methodReflection->class . "::" . $methodReflection->getName();

		$params["class"] = str_replace($this->config["appNamespacePrefix"] . "\\", $this->config["testNamespacePrefix"] . "\\", $methodReflection->class) . $this->config["testClassSuffix"];
		$params["method"] = $this->config["testMethodPrefix"] . ucfirst($methodReflection->getName());

		$params["full"] = $params["class"] . "::" . $params["method"];

		return $params;
	}

}
