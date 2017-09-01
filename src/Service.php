<?php

namespace ADT\PresenterTestCoverage;


use Nette\Application\IPresenter;
use Nette\DI\Container;
use Nette\Utils\Strings;

class Service {

	protected $container;

	/**
	 * @param \Nette\DI\Container $container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/** @var array */
	protected $config = [];

	/**
	 * @param array $config
	 */
	public function setConfig(array $config = []) {
		$this->config = $config;
	}

	/**
	 * Vrátí pole URL adres z jednotlivých testů
	 *
	 * @return array
	 */
	public function getUrls() {
		$urls = [];

		foreach ($this->getFoundMethods() as $method) {
			$params = explode("::", $method);

			$class = new $params[0];
			$urls += $class->$params[1]();
		}

		return $urls;
	}

	/**
	 * Vrátí pole Class::method nalezených tříd s URL
	 *
	 * @return array
	 */
	public function getFoundMethods() {
		$methods = [];
		foreach ($this->getPresenterMethods() as $method) {
			if (self::isValidUrlClassAndMethod($method["class"], $method["method"])) {
				$methods[] = $method["full"];
			}
		}

		return $methods;
	}

	/**
	 * Vrátí pole Class::method nenalezených tříd s URL
	 *
	 * @return array
	 */
	public function getMissingMethods() {
		$methods = [];
		foreach ($this->getPresenterMethods() as $method) {
			if (!self::isValidUrlClassAndMethod($method["class"], $method["method"])) {
				$methods[] = $method["full"];
			}
		}

		return $methods;
	}

	/**
	 * @return array
	 */
	protected function getPresenterMethods() : array {
		$presenters = $this->container->findByType(IPresenter::class);

		$methods = [];
		foreach ($presenters as $serviceNumber) {

			/** @var PresenterComponentReflection $reflection */
			$presenterReflection = $this->container->getService($serviceNumber)->reflection;

			if (!$this->isTestedClass($presenterReflection->getName())) {
				continue;
			}

			$reflectionMethods = array_filter($presenterReflection->getMethods(), function (\Nette\Reflection\Method $methodReflection) {

				if (!$this->isTestedClass($methodReflection->class) || !$methodReflection->isPublic()) {
					return NULL;
				}

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

		$params["class"] = str_replace($this->config["appNamespacePrefix"] . "\\", $this->config["crawlerNamespacePrefix"] . "\\", $methodReflection->class);
		$params["method"] = $methodReflection->getName();

		$params["full"] = $params["class"] . "::" . $params["method"];

		return $params;
	}

	/**
	 * @param string $class
	 * @param string $method
	 * @return bool
	 */
	protected static function isValidUrlClassAndMethod(string $class, string $method) : bool {

		// neexistuje třída nebo metoda
		if (!method_exists($class, $method)) {
			return FALSE;
		}

		// metoda nevrací neprázdné pole
		$class = new $class;
		return (bool) $class->$method();
	}
}
