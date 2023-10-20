<?php

namespace ADT\PresenterTestCoverage;

use Doctrine\ORM\EntityManagerInterface;
use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;

class Service
{
	protected static string $testMethodPrefix = 'action';

	protected array $config = [];
	protected ?RobotLoader $robotLoader = null;
	protected static ?EntityManagerInterface $em;


	public function setConfig(array $config = []): self
	{
		$this->config = $config;
		self::$em = $this->config['em'];
		return $this;
	}


	public function getFoundMethods(): array
	{
		$methods = [];
		foreach ($this->getMethods() as $_method) {
			if ($this->isMethodCovered($_method)) {
				$methods[$_method] = $_method;
			}
		}

		return $methods;
	}


	public function getMissingMethods(): array
	{
		$methods = [];
		foreach ($this->getMethods() as $_method) {
			if (! $this->isMethodCovered($_method)) {
				$methods[$_method] = $_method;
			}
		}

		return $methods;
	}


	public function getUrls(?string $prefix = null): array
	{
		$urls = [];
		foreach ($this->getFoundMethods() as $method) {
			if ($prefix && !Strings::startsWith($method, $prefix)) {
				continue;
			}

			list($class, $method) = explode('::', $method);

			$urls = array_merge($urls, (new $class)->$method());
		}

		return $urls;
	}


	protected function getMethods() : array
	{
		$methods = [];
		foreach ($this->getRobotLoader()->getIndexedClasses() as $_className => $_classFile) {
			if (! Strings::startsWith($_classFile, $this->config['presenterDir'] . '/')) {
				continue;
			}

			if (! Strings::endsWith($_classFile, 'Presenter.php')) {
				continue;
			}

			require_once $_classFile;

			$_presenterReflection = new \ReflectionClass($_className);

			foreach ($_presenterReflection->getMethods() as $_presenterMethodReflection) {
				if (! static::isMethodToTest($_presenterMethodReflection->getName())) {
					continue;
				}

				$methods[] = $this->getTestClassAndMethod($_presenterReflection->getName(), $_presenterMethodReflection->getName());
			}
		}

		return $methods;
	}


	protected static function isMethodToTest(string $methodName) : bool
	{
		if (Strings::startsWith($methodName, static::$testMethodPrefix)) {
			return true;
		}

		return false;
	}


	protected function getTestClassAndMethod(string $presenterClass, string $presenterMethod): string
	{
		return
			str_replace(
				$this->config["appNamespacePrefix"] . '\\',
				$this->config['crawlerNamespacePrefix'] . '\\',
				$presenterClass
			)
			. '::' . $presenterMethod;
	}


	protected function isMethodCovered(string $testMethod) : bool
	{
		list($testClass, $testMethod) = explode('::', $testMethod);

		if (!isset($this->getRobotLoader()->getIndexedClasses()[$testClass])) {
			return false;
		}

		require_once $this->getRobotLoader()->getIndexedClasses()[$testClass];

		// neexistuje třída nebo metoda
		if (!method_exists($testClass, $testMethod)) {
			return false;
		}

		$urls = (new $testClass)->$testMethod();

		// metoda musí vracet neprázdné pole
		return $urls && is_array($urls);
	}


	public function getRobotLoader(): RobotLoader
	{
		if (!$this->robotLoader) {
			$this->robotLoader = (new RobotLoader)
				->addDirectory($this->config['testDir'])
				->addDirectory($this->config['presenterDir'])
				->setTempDirectory($this->config['tempDir']);
		}

		return $this->robotLoader;
	}


	public static function getEm(): ?EntityManagerInterface
	{
		return self::$em;
	}
}
