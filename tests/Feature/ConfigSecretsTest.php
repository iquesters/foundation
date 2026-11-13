<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Iquesters\Foundation\Support\ConfSecret;
use Iquesters\Foundation\Tests\TestCase;

class ConfigSecretsTest extends TestCase
{
    private int $confId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->confId = DB::table('master_data')->insertGetId([
            'key' => 'demo-conf', 'value' => 'Demo', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function meta(string $key, string $value): void
    {
        DB::table('master_data_metas')->insert([
            'ref_parent' => $this->confId, 'meta_key' => $key, 'meta_value' => $value,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function stored(string $key): ?string
    {
        return DB::table('master_data_metas')->where('ref_parent', $this->confId)->where('meta_key', $key)->value('meta_value');
    }

    public function test_command_encrypts_only_plain_secrets_and_is_idempotent(): void
    {
        $this->meta('$DEMO~CLIENT_SECRET', 'plain-secret');
        $this->meta('$DEMO~ACCESS_TOKEN', '');
        $this->meta('$DEMO~PHONE_NUMBER_ID', '12345');

        $this->artisan('conf:encrypt-secrets', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('plain-secret', $this->stored('$DEMO~CLIENT_SECRET'));

        $this->artisan('conf:encrypt-secrets')->expectsOutputToContain('1 secret value(s) encrypted.')->assertSuccessful();

        $encrypted = $this->stored('$DEMO~CLIENT_SECRET');
        $this->assertTrue(ConfSecret::isEncrypted($encrypted));
        $this->assertSame('plain-secret', ConfSecret::decrypt($encrypted));
        $this->assertSame('', $this->stored('$DEMO~ACCESS_TOKEN'));
        $this->assertSame('12345', $this->stored('$DEMO~PHONE_NUMBER_ID'));

        $this->artisan('conf:encrypt-secrets')->expectsOutputToContain('0 secret value(s) encrypted.')->assertSuccessful();
        $this->assertSame($encrypted, $this->stored('$DEMO~CLIENT_SECRET'));
    }

    public function test_config_screen_encrypts_new_secret_and_blank_keeps_current(): void
    {
        $module = DB::table('modules')->insertGetId([
            'uid' => (string) Str::ulid(), 'name' => 'demo', 'description' => 'Demo', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->meta('$DEMO~CLIENT_SECRET', ConfSecret::encrypt('old-secret'));
        $this->meta('APP_NAME', 'Old');

        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)->put("/modules/config/{$module}", [
            'config_keys' => ['$DEMO~CLIENT_SECRET', 'APP_NAME'],
            'config_values' => ['', 'New'],
        ])->assertRedirect();

        $this->assertSame('old-secret', ConfSecret::decrypt($this->stored('$DEMO~CLIENT_SECRET')));
        $this->assertSame('New', $this->stored('APP_NAME'));

        $this->actingAs($admin)->put("/modules/config/{$module}", [
            'config_keys' => ['$DEMO~CLIENT_SECRET'],
            'config_values' => ['rotated-secret'],
        ])->assertRedirect();

        $stored = $this->stored('$DEMO~CLIENT_SECRET');
        $this->assertTrue(ConfSecret::isEncrypted($stored));
        $this->assertSame('rotated-secret', ConfSecret::decrypt($stored));
    }

    public function test_config_screen_is_admin_only(): void
    {
        $this->putJson('/modules/config/1', [])->assertUnauthorized();
        $this->actingAs($this->makeUser('user'))->put('/modules/config/1', [])->assertForbidden();
    }
}
