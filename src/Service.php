<?php

namespace ADT\PresenterTestCoverage;

use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;

class Service
{
	protected static string $testMethodPrefix = 'action';

	protected array $config = [];
	protected ?RobotLoader $robotLoader = null;

	/**
	 * @var array
	 * Array of Arrays -> obsahuje informace o komponentach, ktere mame kontrolovat
	 */
	private $coveredComponents = [];

	/**
	 * @var array
	 * Pole obsahujici jemna komponent ktere nejsou dobre nakonfigurovane a neni mozne je otestovat
	 */
	private $skippedForMissingConfiguration = [];

	public function setConfig(array $config = []): self
	{
		$this->config = $config;
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

	//jenom ziskava metody podle daneho nazvu
	protected function getMethods() : array
	{
		$methods = [];
		foreach ($this->getRobotLoader()->getIndexedClasses() as $_className => $_classFile) {

			//kontrola jestli se jedna o neco co ma byt pokryto testy
			$notFound = true;
			$section = '';
			foreach($this->coveredComponents as $key => $componentDir){
				//kontrola jestli dany soubor je soucasti souboru, ktere obsahuji implementaci pro kterou by mel existovat test.
				if (! Strings::startsWith($_classFile, $componentDir['dir'] . '/')) {
					continue;
				}
				//naleyen, nastavime notFound na false a ukoncime iterovani
				$notFound = false;
				$section = $componentDir['section'];
				break;
			}

			//pokud jsme dany soubor nenasli, budeme pokracovat dalsi iteraci
			if($notFound) {
				continue;
			}

			if(isset($this->config['componentCoverage'][$section]['fileMask'])){
				$mask = $this->config['componentCoverage'][$section]['fileMask'];
			} else {
				//defaultni maska souboru nazev sekce + php
				$mask = ucfirst($section).".php";
			}

			if (! Strings::endsWith($_classFile, $mask)) {
				continue;
			}

			require_once $_classFile;

			$_presenterReflection = new \ReflectionClass($_className);

			//maska metody, chceme zpracovat jenom mentody ktere maji danou masku
			$methodMask = null;
			if(isset($this->config['componentCoverage'][$section]['methodMask'])){
				$methodMask = $this->config['componentCoverage'][$section]['methodMask'];
			}

			foreach ($_presenterReflection->getMethods() as $_presenterMethodReflection) {

				if (! static::isMethodToTest($_presenterMethodReflection->getName(), $methodMask)) {
					continue;
				}
				$methods[] = $this->getTestClassAndMethod($_presenterReflection->getName(), $_presenterMethodReflection->getName());
			}
		}
		return $methods;
	}

	protected static function isMethodToTest(string $methodName, ?string $prefix = null) : bool
	{
		//muzeme menit prefix metody.
		if(is_null($prefix)){
			$prefix = static::$testMethodPrefix;
		}

		if (Strings::startsWith($methodName, $prefix)) {
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
				->setTempDirectory($this->config['tempDir']);

			//iterace pres vsechny nakonfigurovane slozky
			foreach($this->config['componentCoverage'] as $key => $dirSetup){

				//kontrola ze pro danou slozku mame nakonfigurovany obe potrebne slozky. Pokud jednu nemame, tak skipneme, ale po dokonceni behu testu
				//budeme uzivatele informovat o tom ze neco nema dobre nakonfigurovane.
				if(!array_key_exists('componentDir', $dirSetup) || !array_key_exists('testDir', $dirSetup)) {
					//nemame bud componentu, nebo testy -> nelze zpracovat
					$this->skippedForMissingConfiguration[] = $key;
					continue;
				}

				//nastaveni ktere slozky se budou indexovat/
				$this->robotLoader->addDirectory($dirSetup['componentDir']);
				$this->robotLoader->addDirectory($dirSetup['testDir']);
				//nastavime si pro ktere vsechny slozky budeme pracovat
				$this->coveredComponents[] = ["dir" => $dirSetup['componentDir'], "section" => $key];
				//tu bude treba zpracovat jednotliva pole z testCoverage
			}

		}

		return $this->robotLoader;
	}

	public function getSkippedForMissingConfiguration(){
		return $this->skippedForMissingConfiguration;
	}
}
