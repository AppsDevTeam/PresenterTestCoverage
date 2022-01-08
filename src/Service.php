<?php

namespace ADT\PresenterTestCoverage;

use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;

class Service
{
	protected static array $testMethodPrefixes = ['action', 'render'];

	protected array $config = [];
	protected ?RobotLoader $robotLoader = null;

	public function setConfig(array $config = []): self
	{
		$this->config = $config;
		return $this;
	}

	public function getFoundMethods(): array
	{
		return $this->getMethods(true);
	}

	public function getMissingMethods(): array
	{
		return $this->getMethods(false);
	}

	public function getUrls(): array
	{
		$urls = [];

		foreach ($this->getFoundMethods() as $method) {
			$params = explode('::', $method);

			$class = new $params[0];
			$urls = array_merge($urls, $class->{$params[1]}());
		}

		return $urls;
	}

	protected function getMethods(bool $foundMethods = true) : array
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

				list($testClass, $testMethod) = $this->getTestClassAndMethod($_presenterReflection->getName(), $_presenterMethodReflection->getName());

				$isMethodCovered = $this->isMethodCovered($testClass, $testMethod);

				if ($foundMethods && $isMethodCovered || !$foundMethods && !$isMethodCovered) {
					$methods[$testClass . '::' . $testMethod] = $testClass . '::' . $testMethod;
				}
			}
		}

		return $methods;
	}

	protected static function isMethodToTest(string $methodName) : bool
	{
		foreach (static::$testMethodPrefixes as $methodPrefix) {
			if (Strings::startsWith($methodName, $methodPrefix)) {
				return true;
			}
		}

		return false;
	}

	protected function getTestClassAndMethod(string $presenterClass, string $presenterMethod): array
	{
		return [
			str_replace(
				$this->config["appNamespacePrefix"] . '\\',
				$this->config['crawlerNamespacePrefix'] . '\\',
				$presenterClass
			),
			str_replace(
				static::$testMethodPrefixes,
				'test',
				$presenterMethod
			)
		];
	}

	protected function isMethodCovered(string $testClass, string $testMethod) : bool
	{
		require_once $this->getRobotLoader()->getIndexedClasses()[$testClass];

		// neexistuje třída nebo metoda
		if (!method_exists($testClass, $testMethod)) {
			return FALSE;
		}

		$urls = (new $testClass)->$testMethod();

		// metoda musí vracet neprázdné pole
		return $urls && is_array($urls);
	}

	protected function getRobotLoader(): RobotLoader
	{
		if (!$this->robotLoader) {
			$this->robotLoader = (new RobotLoader)
				->addDirectory($this->config['testDir'])
				->addDirectory($this->config['presenterDir'])
				->setTempDirectory($this->config['tempDir']);
		}

		return $this->robotLoader;
	}
}
