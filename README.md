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
    crawlerNamespacePrefix: Url
    presenterDir: %appDir%/Modules
    tempDir: %tempDir%
    testDir: %appDir%/../../tests/url
```


### 2.1 Spuštění commandu
```
$ php www/index.php --env local adt:presenterTestCoverage
```


