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
    appNamespacePrefix: App
    crawlerNamespacePrefix: Crawler
    presenterDir: %appDir%/Modules
    tempDir: %tempDir%
    testDir: %appDir%/../tests/Crawler
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

	public function renderNew()
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
	public function testNew()
	{
		return [
			'/diary/new'
		];
	}
}
```

### 2.1 Spuštění commandu
```
$ php www/index.php adt:presenterTestCoverage
```


