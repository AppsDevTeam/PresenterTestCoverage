<?php

namespace ADT\PresenterTestCoverage;

use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;

class Service
{

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


	/**
	 * @var array testy, ktere se nam podarilo najit v prislusne slozce
	 */
	private $existingTests = [];

	/**
	 * @var array obsahuje soupis testu, ktere pokryvaji metody, ktere mame na projektu
	 */
	private $foundTests = [];

	/**
	 * @var array obsahuje soupis testu, ktere je treba vytvorit aby doslo k pokryti vsech zadanych slozek
	 */
	private $missingTests = [];

	/**
	 * @var array obsahuje vsechny metory, ktere maji byt pokryty testy
	 */
	private $methodsToCover = [];

	/**
	 * @var ?string prefix pro namespace testu. Ma jenom informativni charakter pro uzivatele
	 */
	private $testNamespacePrefix = null;


	/**
	 * @param array $config
	 */
	public function setConfig(array $config = []): self
	{
		$this->config = $config;
		return $this;
	}


	/**
	 * Vraci pole obsahujici nalezene testovaci metody pokryvajici zadanou slozku
	 * @throws \ReflectionException
	 */
	public function getFoundMethods(): array
	{
		//pokud je pole prazdne, je sance ze se jeste nic nehledalo -> prohledame
		if (empty($this->foundTests)) {
			$this->findAvailableTests();
			$this->cehckCoverage();
		}
		return $this->foundTests;
	}


	/**
	 * Vraci pole obsahujici testovaci metody, ktere se nepodarilo nalezt a jsou treba k pokryti
	 * @throws \ReflectionException
	 */
	public function getMissingMethods(): array
	{
		//prazdne pole -> asi se nehledalo -> prohledame
		if (empty($this->missingTests)) {
			$this->findAvailableTests();
			$this->cehckCoverage();
		}
		return $this->missingTests;
	}


	/**
	 * Kontrola zda se jedna o metodu, ktera je urcena nastavenim k otestovani.
	 * @param string $methodName
	 * @param string|null $prefix
	 */
	protected static function isMethodToTest(string $methodName, string $prefix = null) : bool
	{
		if (Strings::startsWith($methodName, $prefix)) {
			return true;
		}

		return false;
	}


	/**
	 * metoda kontroluje pokrzti metod testy.
	 * @throws \ReflectionException
	 */
	public function cehckCoverage(){

		//uz se asi jednou poustelo, tedy neni treba to delat znovu
		if (!empty($foundTests) || !empty($missingTests)) {
			return;
		}

		$this->findMethodsToCover();

		foreach ($this->methodsToCover as $method) {

			//pozice prvniho backslashe
			$position = strpos($method, '\\');

			if ($position) {
				$method = substr($method, $position + 1);
			}

			if (array_key_exists($method, $this->existingTests)) {
				$this->foundTests[] = $this->existingTests[$method];
			} else {
				$this->missingTests[] = $this->testNamespacePrefix .'\\'. $method;
			}
		}
	}


	/**
	 * Nacteni konfigurace pro vyheldavani a nacteni vsech souboru ktere jsou zadany
	 */
	public function getRobotLoader(): RobotLoader
	{
		if (!$this->robotLoader) {
			$this->robotLoader = (new RobotLoader)
				->setTempDirectory($this->config['tempDir']);

			// iterace pres vsechny nakonfigurovane slozky
			foreach ($this->config['componentCoverage'] as $key => $dirSetup) {

				// kontrola ze pro danou slozku mame nakonfigurovany obe potrebne slozky. Pokud jednu nemame, tak skipneme, ale po dokonceni behu testu
				// budeme uzivatele informovat o tom ze neco nema dobre nakonfigurovane.
				if(!array_key_exists('componentDir', $dirSetup)
					|| !array_key_exists('fileMask', $dirSetup)
					|| !array_key_exists('methodMask', $dirSetup)
					|| !array_key_exists('testDir', $this->config)) {
					//nemame bud componentu, nebo testy -> nelze zpracovat
					$this->skippedForMissingConfiguration[] = $key;
					continue;
				}

				// nastaveni ktere slozky se budou indexovat/
				$this->robotLoader->addDirectory($dirSetup['componentDir']);
				$this->robotLoader->addDirectory($this->config['testDir']);

				if (!isset($this->testNamespacePrefix)) {
					$this->guessTestNamespace($this->config['testDir'], $dirSetup['componentDir']);
				}

				// nastavime si pro ktere vsechny slozky budeme pracovat
				$this->coveredComponents[] = ["dir" => $dirSetup['componentDir'], "section" => $key];
			}
		}
		return $this->robotLoader;
	}


	/**
	 * Pouze getter, vraci informace o selhani nastaveni konfigurace
	 */
	public function getSkippedForMissingConfiguration() {
		return $this->skippedForMissingConfiguration;
	}


	/**
	 * Metoda vyhleda vsechny soubory podle zadane masky a vrati pole.
	 * @throws \ReflectionException
	 */
	protected function findMethodsToCover(): void {

		if (!empty($this->methodsToCover)) {
			return;
		}

		foreach ($this->getRobotLoader()->getIndexedClasses() as $_className => $_classFile) {

			// kontrola jestli se jedna o neco co ma byt pokryto testy
			$notFound = true;
			$section = '';
			foreach ($this->coveredComponents as $key => $componentDir) {
				// kontrola jestli dany soubor je soucasti souboru, ktere obsahuji implementaci pro kterou by mel existovat test.
				if (! Strings::startsWith($_classFile, $componentDir['dir'] . '/')) {
					continue;
				}
				// nalezen, nastavime notFound na false a ukoncime iterovani
				$notFound = false;
				$section = $componentDir['section'];
				break;
			}

			// pokud jsme dany soubor nenasli, budeme pokracovat dalsi iteraci
			if ($notFound) {
				continue;
			}

			$mask = $this->config['componentCoverage'][$section]['fileMask'];

			if (! Strings::endsWith($_classFile, $mask)) {
				continue;
			}

			require_once $_classFile;

			$_presenterReflection = new \ReflectionClass($_className);

			//nechceme zpracovavat abstratni tridy -> toz continue
			if ($_presenterReflection->isAbstract()) {
				continue;
			}

			// maska metody, chceme zpracovat jenom metody ktere maji danou masku
			$methodMask = null;
			if (isset($this->config['componentCoverage'][$section]['methodMask'])) {
				$methodMask = $this->config['componentCoverage'][$section]['methodMask'];
			}

			foreach ($_presenterReflection->getMethods() as $_presenterMethodReflection) {
				//testy na abstraktni metody nemaji smysl -> continue
				if ($_presenterMethodReflection->isAbstract()) {
					continue;
				}

				if (! static::isMethodToTest($_presenterMethodReflection->getName(), $methodMask)) {
					continue;
				}

				//vytvareni soupisu metod, pro ktere budeme chtit hledat testy
				$this->methodsToCover[] = $_presenterReflection->getName()."::".$_presenterMethodReflection->getName();
			}
		}
	}


	/**
	 * metoda najde vsechny dostupne testy, ktere mame a ulozi si je
	 */
	public function findAvailableTests(): void {

		$testDir = $this->config['testDir'];

		foreach ($this->getRobotLoader()->getIndexedClasses() as $_className => $_classFile) {
			$namespaceArray = null;

			$crawlerBasePath = realpath($testDir) . '/';

			//pokud nejsme v testovaci slozce, tak nas to nezajima
			if (! Strings::startsWith($_classFile, $crawlerBasePath)) {
				continue;
			}

			//mame cestu k souboru, protoze namespace odpovida ceste k souboru, mame v podstate namespace bez prvniho elementu
			$classFileRootedPath = str_replace($crawlerBasePath, '', $_classFile);

			require_once $_classFile;

			$_crawlerReflection = new \ReflectionClass($_className);

			//kontrola jestli je dana trida abstraktni ci nikoliv
			if ($_crawlerReflection->isAbstract()) {
				continue;
			}

			foreach ($_crawlerReflection->getMethods() as $_crawlerMethodReflection) {
				//testy na abstraktni metody nemaji smysl -> continue
				if ($_crawlerMethodReflection->isAbstract()) {
					continue;
				}

				//konverze nazvu na "kratky namespace"
				$shortNamespace = substr($classFileRootedPath, 0, strrpos($classFileRootedPath, '.'));

				$this->existingTests[str_replace('/', '\\',$shortNamespace)."::".$_crawlerMethodReflection->getName()] = $_className."::".$_crawlerMethodReflection->getName();

			}
		}
	}


	/**
	 * Pkud metodu nenajdeme, je potreba o tomto faktu informovat uzivatele, potrebujeme mu tedy predat informaci o
	 * namespace kve kterem by se metoda mela nachazet -> je potreba nejak uhodnout strukturu nad prvni urovni pod rootem
	 * namespaceu
	 * @param string $testDir
	 * @param string $componentDir
	 */
	public function guessTestNamespace(string $testDir, string $componentDir): void {
		if ($this->testNamespacePrefix) {
			return;
		}

		$componentArr = explode('/', $componentDir);
		$testArr = explode('/', $testDir);

		$limit = (count($componentArr) < count($testArr)) ? count($componentArr) : count($testArr);

		for ($i = 0; $i < $limit; $i++) {
			if ($componentArr[$i] === $testArr[$i]) {
				unset($testArr[$i]);
				continue;
			}
		}

		foreach ($testArr as $key => $value) {
			if ($value === '..') {
				unset($testArr[$key]);
			}
		}

		$this->testNamespacePrefix = ucfirst(count($testArr) ? implode('\\', $testArr) : '');
	}
}