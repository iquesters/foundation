<?php

namespace Iquesters\Foundation\Tests\Unit;

use Illuminate\Support\Facades\File;
use Iquesters\Foundation\Support\PackageAsset;
use Iquesters\Foundation\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PackageAssetTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/pkg-asset-'.uniqid();
        File::makeDirectory("{$this->base}/public/js", 0777, true);
        File::makeDirectory("{$this->base}/public/.hidden", 0777, true);
        File::makeDirectory("{$this->base}/public-other", 0777, true);
        file_put_contents("{$this->base}/public/js/app.js", 'ok');
        file_put_contents("{$this->base}/public/.hidden/x.js", 'no');
        file_put_contents("{$this->base}/public/notes.php", '<?php');
        file_put_contents("{$this->base}/public-other/leak.js", 'no');
        file_put_contents("{$this->base}/.env", 'APP_KEY=secret');
        file_put_contents("{$this->base}/composer.json", '{}');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_serves_files_inside_public(): void
    {
        $this->assertSame(realpath("{$this->base}/public/js/app.js"), PackageAsset::resolve("{$this->base}/public", 'js/app.js'));
    }

    public static function blocked(): array
    {
        return [
            'parent .env' => ['../.env'],
            'parent composer' => ['../composer.json'],
            'nested traversal' => ['js/../../.env'],
            'sibling dir with same prefix' => ['../public-other/leak.js'],
            'hidden folder' => ['.hidden/x.js'],
            'php file' => ['notes.php'],
            'missing' => ['js/missing.js'],
            'directory' => ['js'],
            'null byte' => ["js/app.js\0.png"],
            'empty' => [''],
        ];
    }

    #[DataProvider('blocked')]
    public function test_blocks_unsafe_paths(string $path): void
    {
        $this->assertNull(PackageAsset::resolve("{$this->base}/public", $path));
    }
}
