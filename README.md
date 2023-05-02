# Component test coverage

Command pro Nette, který hlídá, že programátor nezapomněl přidat akceptační test k nově vytvořenému presenteru, gridu, formuláři a jiným komponentám. Umí také hlídat chybějící testy k nově přidaným akcím u presenterů a nově přidaným render* metodám u gridů a formulářů.

Typicky se command spouští před akceptačními testy (např. od Codeception) v rámci CI případně již dřív před pushnutím do repozitáře. Jde o to, aby se programátor včas dozvěděl, že něco chybí a že má test vytvořit.

Konfigurace umožňuje přizpůsobení i pro další komponenty a pro jiný framework než je Codeception.

## 1 Instalace do aplikace
Composer:
```
composer require adt/presenter-test-coverage
```

### 1.1 Registrace extension
```
# app/config/config.neon
extensions:
    componentTestCoverage: ADT\ComponentTestCoverage\DI\ComponentTestCoverageExtension
```

### 1.2 Nastavení
```
# app/config/config.neon
componentTestCoverage:
    tempDir: %appDir%/../temp
    
    # Složka s akceptačními testy. V této složce se kontroluje, zda nechybí nějaké testy oproti složkám s aplikací, které se definují dále.
    # V této složce mohou být testy navíc, to ničemu nevadí. Struktura v této složce musí odpovídat struktuře ve složkách s aplikací.
    testDir: %appDir%/../tests/Acceptance
    
    componentCoverage:
        clientModulePresenters:    # Název kategorie testovaných věcí. Libovolný název.
            componentDir: %appDir%/Modules/ClientModule    # Složka s implementací daných komponent
            appFileMask: (.*)Presenter.php    # Regulární výraz: pro které soubory v app složce se má kontrolovat existence testů
            testFileMask: {$1}PresenterCest.php    # Maska souborů v test složce, pokud testovací framework potřebuje speciální suffix. Např.: Pokud je presenter v `%appDir%/Modules/ClientModule/ClientPresenter.php`, bude se k němu hledat soubor s testem v `<testDir>/Modules/ClientModule/ClientPresenterCest.php`.
            methodMask: action.*    # Regulární výraz: pro které metody se mají hledat metody v testech. Aktuálně se jména musí shodovat 1:1.
        grids:
            componentDir: %appDir%/Components/Grids
            appFileMask: (.*)Grid.php
            testFileMask: {$1}GridCest.php
            methodMask: render.*
	...
```


## 2 Spuštění commandu
```
$ php bin/console adt:component-test-coverage
```


## 3 Příklad

### 3.1 Testovaná komponenta

```
namespace App\Modules\ClientModule;

class ClientPresenter extends BasePresenter
{
	public function actionNew()
	{
		// some code
	}
}
```

### 3.2 Testovací třída

```
namespace Tests\Acceptance\Modules\ClientModule;

class ClientPresenterCest extends BasePresenterCest
{
	public function actionNew(AcceptanceTester $I)
	{
		$I->crawl([
			'/client/new',
		]);
	}
}
```

Kde implementace funkce `crawl` už je mimo tuto komponentu a každý projekt si řeší implementaci samotného testu sám. Příklad by mohl být:

```
public function crawl(array $urls)
{
	$I = $this;
	foreach ($urls as $url) {
		$I->amOnPage($url);
		$I->dontSee('chyba 404');
		$I->dontSee('chyba 500');
		$I->dontSee('Nedostatečná práva');
	}
}
```

U složitějších komponent se pak typicky píšou složitější testy. U gridů například na nastavení filtrů a odeslání filtrace, proklikání stránkování, apod. U formulářů pak například na otestování změny hodnoty a uložení, apod.

