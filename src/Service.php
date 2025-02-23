<?php

namespace ADT\PresenterTestCoverage;

use Nette\Loaders\RobotLoader;
use Nette\Utils\Strings;
use Nette\Schema\Expect;


class ComponentCoverageException extends \Exception {

}


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
	 * @var array obsahuje vsechny metody, ktere maji byt pokryty testy
	 */
	private $methodsToCover = [];

	/**
	 * @var array - obsahuje preklady na testovaci soubor pokud je zadana testFile mask. Preklad z
	 */
	private $spceialFiles = [];

	/**
	 * @var array - obsahuje soubory, ktere neni mozne zpracovat pro nekompatibilnost s PSR4
	 */
	private $pSR4Incompatible = [];

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
		// pokud je pole prazdne, je sance ze se jeste nic nehledalo -> prohledame
		if (empty($this->foundTests)) {
			$this->findExistingTests();
			$this->checkCoverage();
		}
		return $this->foundTests;
	}


	/**
	 * Vraci pole obsahujici testovaci metody, ktere se nepodarilo nalezt a jsou treba k pokryti
	 * @throws \ReflectionException
	 */
	public function getMissingMethods(): array
	{
		// prazdne pole -> radeji prohledame
		if (empty($this->missingTests)) {
			$this->findExistingTests();
			$this->checkCoverage();
		}
		return $this->missingTests;
	}


	/**
	 * Metoda vraci pole s url adresami, ktere jsou nalezeny v existujicich testech
	 * @param string|null $prefix
	 * @throws ComponentCoverageException
	 * @throws \ReflectionException
	 */
	public function getUrls(?string $prefix = null): array
	{
		$urls = [];
		foreach ($this->getFoundMethods() as $method) {
			if ($prefix && !Strings::startsWith($method, $prefix)) {
				continue;
			}

			list($classFile, $method) = explode('::', $method);

			$classFile = getcwd()."/".$classFile;

			$toObject = array_search($classFile, $this->getRobotLoader()->getIndexedClasses());

			//Potrebujeme ziskat tridu k prislusnemu souboru
			$urls = array_merge($urls, (new $toObject())->$method());
		}
		return $urls;
	}


	/**
	 * Kontrola zda se jedna o metodu, ktera je urcena nastavenim k otestovani.
	 * @param string $methodName
	 * @param string|null $prefix
	 */
	protected static function isMethodToTest(string $methodName, ?string $prefix = null) : bool
	{
		return self::matchesMask($methodName, '^'.$prefix);

	}


	/**
	 * metoda kontroluje pokryti metod testy.
	 * @throws \ReflectionException
	 */
	protected function checkCoverage(): void {
		$cwd = getcwd().'/';
		if (!empty($this->foundTests) || !empty($this->missingTests)) {
			return;
		}

		foreach ($this->findMethodsToCover() as $key => $method) {
			// ziskame cast namespace podle ktere porovnavame
			$position = strpos($method, '\\');
			if ($position) {
				$method = substr($method, $position + 1);
			}

			/**
			 * Roztrideni podle toho zda test byl nalezen ci nikoliv. Pri vzpisu se odstranuje cest cestz k aplikaci
			 * /var/www/html/tests/Kotatka/Components/Grids/Bonbon/BonbonGrid.php::renderGrid -> tests/Kotatka/Components/Grids/Bonbon/BonbonGrid.php::renderGrid
			 */
			if (array_key_exists($method, $this->existingTests)) {
				$this->foundTests[] = str_replace($cwd, '', $this->existingTests[$method]);
			} else {
				$this->missingTests[] = str_replace($cwd, '', $key);
			}
		}
	}


	/**
	 * Nacteni konfigurace pro vyheldavani a nacteni vsech souboru ktere jsou zadany
	 */
	public function getRobotLoader(): RobotLoader
	{
		if (!$this->robotLoader) {

			// nelze pracovat bez zadane temp slozky -> vyhozeni vyjimky
			if(!array_key_exists('tempDir', $this->config) || !isset($this->config['tempDir'])) {
				throw new ComponentCoverageException('Missing tempDir in presenterTestCoverage:');
			}

			$this->robotLoader = (new RobotLoader)
				->setTempDirectory($this->config['tempDir']);

			/**
			tempDir: %appDir%/../temp
			testDir: %appDir%/../tests/Kotatka  #Rootem je sloza na urovni App
			componentCoverage:
			grids:
			componentDir: %appDir%/Components/Grids
			appFileMask: '(.*)Presenter.php'
			testFileMask: '{$1}PresenterCest.php'
			methodMask: action.*
			 */

			//definice schematu
			$expected = Expect::structure([
				'tempDir' => Expect::string()->required(),
				'testDir' => Expect::string()->required(),
				'componentCoverage' => Expect::arrayOf(
					Expect::structure([
						'componentDir' => Expect::string()->required(),
						'appFileMask' => Expect::string()->required(),
						'testFileMask' => Expect::string(),
						'methodMask' => Expect::string()->required()
					]),
					'string'
				)->required()
			]);

			$processor = new \Nette\Schema\Processor;
			//validace configu
			try{
				$config = $processor->process($expected, $this->config);
			} catch (\Nette\Schema\ValidationException $e) {
				throw new ComponentCoverageException($e->getMessage());
			}

			$this->robotLoader->addDirectory($this->config['testDir']);

			// iterace pres vsechny nakonfigurovane slozky
			foreach ($this->config['componentCoverage'] as $key => $dirSetup) {

				// nastaveni ktere slozky se budou indexovat
				$this->robotLoader->addDirectory($dirSetup['componentDir']);

				// nastavime si pro ktere vsechny slozky budeme pracovat
				$this->coveredComponents[] = ["dir" => $dirSetup['componentDir'], "section" => $key];
			}
			$this->robotLoader->register();
		}

		return $this->robotLoader;
	}


	/**
	 * Pouze getter, vraci informace o tom ktere soubory nelze ypracovat protoze nejsou PSR4 kompatibilni
	 */
	public function getPSR4Incompatible(): array{
		return $this->pSR4Incompatible;
	}


	/**
	 * Metoda vyhleda vsechny soubory podle zadane masky a nastavi je do pole
	 * @throws \ReflectionException
	 */
	protected function findMethodsToCover(): array {


		if (!empty($this->methodsToCover)) {
			return $this->methodsToCover;
		}

		$cwd = getcwd().'/';
		foreach ($this->getRobotLoader()->getIndexedClasses() as $_className => $_classFile) {

			// kontrola jestli se jedna o neco co ma byt pokryto testy
			$section = '';

			foreach ($this->coveredComponents as $key => $componentDir) {

				// kontrola jestli dany soubor je soucasti souboru, ktere obsahuji implementaci pro kterou by mel existovat test.
				if (!Strings::startsWith($_classFile, $componentDir['dir'] . '/')) {
					continue;
				}

				// nalezen, nastavime notFound na false a ukoncime iterovani
				$section = $componentDir['section'];

				$mask = $this->config['componentCoverage'][$section]['appFileMask'];
				if (!self::matchesMask($_classFile, $mask)) {
					continue;
				}

				$_presenterReflection = new \ReflectionClass($_className);

				// nechceme zpracovavat abstraktni tridy -> continue
				if ($_presenterReflection->isAbstract()) {
					continue;
				}

				// maska metody, chceme zpracovat jenom metody ktere maji danou masku
				$methodMask = null;
				if (isset($this->config['componentCoverage'][$section]['methodMask'])) {
					$methodMask = $this->config['componentCoverage'][$section]['methodMask'];
				}

				foreach ($_presenterReflection->getMethods() as $_presenterMethodReflection) {
					// testy na abstraktni metody nemaji smysl -> continue
					if ($_presenterMethodReflection->isAbstract()) {
						continue;
					}

					if (!static::isMethodToTest($_presenterMethodReflection->getName(), $methodMask)) {
						continue;
					}

					// potrebujeme ziskat cestu k souboru v mistni slozce, ta odpovida namespace -> smazeme to co je pred namespacem
					$postionoOfNamespace = stripos($_presenterReflection->getFileName(), str_replace('\\', '/', $_presenterReflection->getName()));

					//PSR4 nekompatibilni strukturu nelze zpracovat a ukoncujeme praci s timto souborem
					if($postionoOfNamespace === false){
						$this->pSR4Incompatible[] = str_replace($cwd, '', $_presenterReflection->getFileName());
						break;
					}


					/**
					 * potrebujeme se zbavit vseho co je pred casti odpovidajici namespace
					 * /var/www/html/app/Components/Grids/Bonbon/BonbonGrid.php -> app/Components/Grids/Bobon/BonbonGrid.php
					 */
					$filePath = substr($_presenterReflection->getFileName(), $postionoOfNamespace);


					/**
					 * potrebujeme odstranit root sloyku namespace
					 * /app/Components/Grids/Bonbon/BonbonGrid.php -> Components/Grids/Bonbon/BonbonGrid.php
					 */
					$filePath = substr($filePath, (strpos($filePath, '/') + 1));


					/**
					 * Vytvoreni plne cesty k testovacimu souboru
					 * "/var/www/html/tests/Kotatka" . "/" . "Components/Grids/Bonbon/BonbonGrid.php" . "::" . "render" -> /var/www/html/tests/Kotatka/Components/Grids/Bonbon/BonbonGrid.php::render
					 * Pokud budeme mit navic k dispozici masku testovaciho souboru, bude treba prislusne upravit soubor se kterym budeme pracovat
					 */
					if (isset($this->config['componentCoverage'][$section]['testFileMask'])) {
						$mathches = [];
						preg_match("/" . $this->config['componentCoverage'][$section]['appFileMask'] . "/", $filePath, $mathches);

						if (count($mathches) === 2) {
							$filePath = str_replace('{$1}', $mathches[1], $this->config['componentCoverage'][$section]['testFileMask']);
						}
					}

					$testFilePath = realpath($this->config['testDir']) . '/' . $filePath . "::" . $_presenterMethodReflection->getName();

					// vytvareni soupisu metod pro ktere budeme chtit hledat testy
					$this->methodsToCover[$testFilePath] = $filePath . "::" . $_presenterMethodReflection->getName();
				}
			}
		}
		return $this->methodsToCover;
	}


	/**
	 * metoda najde vsechny dostupne testy, ktere mame a ulozi si je
	 */
	protected function findExistingTests(): array {

		// pokud se jiz jednou sestavilo pole testu, neni treba hledat -> return
		if(!empty($this->existingTests)){
			return $this->existingTests;
		}

		$testDir = $this->config['testDir'];
		foreach ($this->getRobotLoader()->getIndexedClasses() as $_className => $_classFile) {
			$namespaceArray = null;

			$crawlerBasePath = realpath($testDir) . '/';

			//pokud nejsme v testovaci slozce, soubor neni treba kontrolovat
			if (! Strings::startsWith($_classFile, $crawlerBasePath)) {
				continue;
			}

			/**
			 * mame cestu k souboru, protoze namespace odpovida ceste k souboru, mame v podstate namespace bez prvniho elementu
			 * /var/www/html/tests/Kotatka/Components/Grids/Bonbon/BonbonGrid.php -> Components/Grids/Bonbon/BonbonGrid.php
			 */
			$classFileRootedPath = str_replace($crawlerBasePath, '', $_classFile);

			$_crawlerReflection = new \ReflectionClass($_className);

			if ($_crawlerReflection->isAbstract()) {
				continue;
			}

			foreach ($_crawlerReflection->getMethods() as $_crawlerMethodReflection) {

				if ($_crawlerMethodReflection->isAbstract()) {
					continue;
				}

				/**
				 * konverze cesty k souboru na "kratky namespace"
				 * Components/Grids/Bonbon/Bonbon.php -> Components/Grids/Bonbon/BonbonGrid
				 */
				$shortNamespace = substr($classFileRootedPath, 0, strrpos($classFileRootedPath, '.'));

				/**
				 * "Components/Grids/Bonbon/BonbonGrid" . "::" . "renderGrid" -> "Components/Grids/Bonbon/BonbonGrid::renderGrid"
				 * "/var/www/html/tests/Kotatka/Components/Grids/Bonbon/BonbonGrid.php" . "::" . "renderGrid" -> "/var/www/html/tests/Kotatka/Components/Grids/Bonbon/BonbonGrid.php::renderGrid"
				 */
				$this->existingTests[$classFileRootedPath."::".$_crawlerMethodReflection->getName()] = $_classFile."::".$_crawlerMethodReflection->getName();
			}
		}
		return $this->existingTests;
	}


	/**
	 * Kontrola jestli zadany string odpovida masce
	 * @param string $haystack
	 * @param string $mask
	 */
	protected static function matchesMask(string $haystack, string $mask): bool {
		return preg_match('/'.$mask.'/', $haystack) ? true : false;
	}

}
