<?php

namespace Iquesters\Foundation\Tests\Unit;

use Iquesters\Foundation\Support\UserModelPatcher;
use PHPUnit\Framework\TestCase;

class UserModelPatcherTest extends TestCase
{
    private const TRAIT = 'Iquesters\\UserManagement\\Traits\\HasUserManagement';

    /** Laravel 13's stock App\Models\User. */
    private const STOCK_L13 = <<<'PHP'
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
}
PHP;

    /** Pre-attribute style (Laravel ≤ 12). */
    private const STOCK_L12 = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
PHP;

    public function test_patches_a_laravel_13_model(): void
    {
        [$source, $changes] = UserModelPatcher::patchSource(self::STOCK_L13, [self::TRAIT], ['uid', 'phone', 'status']);

        $this->assertStringContainsString("use Illuminate\\Notifications\\Notifiable;\nuse " . self::TRAIT . ";", $source);
        $this->assertStringContainsString('use HasFactory, Notifiable, HasUserManagement;', $source);
        $this->assertStringContainsString("#[Fillable(['name', 'email', 'password', 'uid', 'phone', 'status'])]", $source);
        $this->assertSame(['Added trait HasUserManagement', 'Made fillable: uid, phone, status'], $changes);
        $this->assertValidPhp($source);
    }

    public function test_patches_a_fillable_property_model(): void
    {
        [$source] = UserModelPatcher::patchSource(self::STOCK_L12, [self::TRAIT], ['uid']);

        $this->assertStringContainsString('use Notifiable, HasUserManagement;', $source);
        $this->assertMatchesRegularExpression("/'password',\n\s+'uid',\n\s+\];/", $source);
        $this->assertValidPhp($source);
    }

    public function test_is_idempotent(): void
    {
        [$once] = UserModelPatcher::patchSource(self::STOCK_L13, [self::TRAIT], ['uid']);
        [$twice, $changes] = UserModelPatcher::patchSource($once, [self::TRAIT], ['uid']);

        $this->assertSame($once, $twice);
        $this->assertSame([], $changes);
    }

    public function test_adds_a_use_statement_when_the_class_has_no_traits(): void
    {
        $source = "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Foundation\\Auth\\User as Authenticatable;\n\nclass User extends Authenticatable\n{\n}\n";

        [$patched] = UserModelPatcher::patchSource($source, [self::TRAIT]);

        $this->assertStringContainsString("{\n    use HasUserManagement;\n", $patched);
        $this->assertValidPhp($patched);
    }

    private function assertValidPhp(string $source): void
    {
        $file = tempnam(sys_get_temp_dir(), 'umt');
        file_put_contents($file, $source);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exit);
        unlink($file);

        $this->assertSame(0, $exit, implode("\n", $output));
    }
}
