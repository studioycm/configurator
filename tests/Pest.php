<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCaseCustom". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Feature');

$independentCommitTests = [
    __DIR__.'/Feature/Catalog/MySql/CatalogGroupTagsConcurrencyTest.php',
    __DIR__.'/Feature/Catalog/CatalogPresentationCommitTest.php',
    __DIR__.'/Feature/Catalog/MySql/CatalogPresentationConcurrencyTest.php',
];
$nonResettingCommitTests = [
    __DIR__.'/Feature/Catalog/MySql/GroupAttributesConcurrencyTest.php',
    __DIR__.'/Feature/Catalog/MySql/CatalogAssetPreparationConcurrencyTest.php',
    __DIR__.'/Feature/Catalog/MySql/CatalogMainImageConcurrencyTest.php',
    __DIR__.'/Feature/Catalog/MySql/CatalogImagesBrowsingTest.php',
];
$imagesCommitTests = array_map(fn (string $path): string => __DIR__.'/Feature/'.$path, [
    'Catalog/CatalogAssetFoundationTest.php', 'Catalog/CatalogAssetSourcesTest.php', 'Catalog/CatalogAssetStorageTest.php',
    'Catalog/CatalogMainImageAssignmentTest.php', 'Catalog/CatalogMainImageRecoveryTest.php',
    'Filament/CatalogMainImageFieldTest.php', 'Filament/CatalogMainImageCompletionTest.php',
]);
$transactionalTests = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/Feature', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getPathname(), [...$independentCommitTests, ...$nonResettingCommitTests, ...$imagesCommitTests], true)) {
        $transactionalTests[] = $file->getPathname();
    }
}
if ($transactionalTests !== []) {
    pest()->use(RefreshDatabase::class)->in(...$transactionalTests);
}
pest()->use(DatabaseMigrations::class)->in(...$independentCommitTests);
if (getenv('DB_CONNECTION') === 'sqlite' && getenv('DB_DATABASE') === ':memory:') {
    pest()->use(DatabaseMigrations::class)->in(...$imagesCommitTests);
} else {
    pest()->extend(TestCase::class)->beforeEach(fn () => throw new LogicException('SQLite-only Images commit proof.'))->in(...$imagesCommitTests);
}

pest()->beforeEach(function (): void {
    config(['catalog-assets.testing_root' => sys_get_temp_dir().'/catalog-images-'.bin2hex(random_bytes(8))]);
    foreach (['local', 'public', 'catalog-assets', 'catalog-asset-imports'] as $disk) {
        catalogImageTestDisk($disk);
    }
    config(['media-library.temporary_directory' => config('catalog-assets.testing_root').'/media-tmp']);
})->in(...[...$imagesCommitTests, __DIR__.'/Feature/Catalog/CatalogAssetDisplayTest.php']);

pest()->afterEach(function (): void {
    if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
        return;
    }
    if (Schema::hasTable('groups')) {
        DB::table('groups')->update(['parent_id' => null]);
    }
})->in(...$imagesCommitTests);

function catalogImageTestDisk(string $disk): LocalFilesystemAdapter
{
    $scratch = config('catalog-assets.testing_root');
    if (! is_string($scratch) || ! str_starts_with($scratch, sys_get_temp_dir().'/catalog-images-') || preg_match('/^[a-z0-9-]+$/D', $disk) !== 1) {
        throw new LogicException('Image fixtures require their explicit scratch-only disk root.');
    }
    $root = $scratch.'/'.$disk;
    $files = new Filesystem;
    $files->ensureDirectoryExists($root);
    $files->cleanDirectory($root);
    $config = array_replace(config('filesystems.disks.'.$disk, []), ['driver' => 'local', 'root' => $root, 'throw' => true]);
    config(['filesystems.disks.'.$disk => $config]);
    $adapter = Storage::build($config);
    Storage::set($disk, $adapter);

    return $adapter;
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//    return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
