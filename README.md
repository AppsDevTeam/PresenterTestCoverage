# Presenter test coverage

## 1 Instalace do aplikace
composer:
```
composer require adt/presenter-test-coverage
```

### 1.1 Registrace extension
```
# app/config/config.neon
extensions:
    presenterTestCoverage: ADT\PresenterTestCoverage\DI\PresenterTestCoverageExtension
```

### 1.2 Nastavení
```
# app/config/config.neon    
presenterTestCoverage:
    tempDir: %appDir%/../temp
    testDir: %appDir%/../tests/Acceptance  # Root slozka ve ktere se nachazi struktura slozek a souboru odpovidajici strukture aplikace
    componentCoverage:    # vycet komponent pro ktere budou testy provadeny
        grids:    # nazev testovane sekce -  REQUIRED
            componentDir: %appDir%/Components/Grids    # slozka s implementacemi - REQUIRED
            fileMask: *    # maska souboru pro ktere se hledaji testy [*|nazev|regex] - REQIRED
            methodMask: *    # maska metod pro ktere se maji hledat testy [*|nazev|regex] - REQIRED
```

### 1.3 Příklad testované třídy
```
namespace App\Modules\ClientModule\Diary;

class DiaryPresenter extends BasePresenter
{
	public function actionNew()
	{
		// some code
	}
}
```

### 1.4 Příklad testovací třídy
```
namespace Crawler\Modules\ClientModule\Diary;

class DiaryPresenter
{
	public function actionNew()
	{
		return [
			'/diary/new'
		];
	}
}
```

### 2 Spuštění commandu
```
$ php bin/console adt:presenterTestCoverage
```

### 3 Příklad crawleru
```
public function crawlerTest(AcceptanceTester $I)
{
	/** @var ADT\PresenterTestCoverage\Service $presenterCoverageService */
	$presenterCoverageService = $this->getService(ADT\PresenterTestCoverage\Service::class);
	foreach ($presenterCoverageService->getUrls('Crawler\Modules\SystemModule') as $link) {
		$I->amOnPage($link);
		$I->dontSee('chyba 404');
		$I->dontSee('chyba 500');
		$I->dontSee('Nedostatečná práva');
	}
}
```