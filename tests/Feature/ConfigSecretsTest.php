<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Iquesters\Foundation\Support\BaseConf;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Support\ConfSecret;
use Iquesters\Foundation\Support\Settings\SettingsStore;
use Iquesters\Foundation\Tests\TestCase;

class ConfigSecretsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ConfProvider::register('iquesters/demo', DemoSecretsConf::class);
    }

    private function storedRaw(): array
    {
        return json_decode((string) DB::table(SettingsStore::TABLE)->where('module', 'iquesters/demo')->value('settings'), true) ?? [];
    }

    public function test_command_encrypts_only_plain_secrets_and_is_idempotent(): void
    {
        // Written directly (bypassing the store), as if imported in plain text.
        DB::table(SettingsStore::TABLE)->insert([
            'module' => 'iquesters/demo',
            'settings' => json_encode(['client_secret' => 'plain-secret', 'access_token' => '', 'phone_number_id' => '12345']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('conf:encrypt-secrets', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('plain-secret', $this->storedRaw()['client_secret']);

        $this->artisan('conf:encrypt-secrets')->expectsOutputToContain('1 secret value(s) encrypted.')->assertSuccessful();

        $stored = $this->storedRaw();
        $this->assertTrue(ConfSecret::isEncrypted($stored['client_secret']));
        $this->assertSame('plain-secret', ConfSecret::decrypt($stored['client_secret']));
        $this->assertSame('', $stored['access_token']);
        $this->assertSame('12345', $stored['phone_number_id']);

        $this->artisan('conf:encrypt-secrets')->expectsOutputToContain('0 secret value(s) encrypted.')->assertSuccessful();
    }

    public function test_config_screen_encrypts_new_secret_and_blank_keeps_current(): void
    {
        $module = DB::table('modules')->insertGetId([
            'uid' => (string) Str::ulid(), 'name' => 'demo', 'description' => 'Demo', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        ConfProvider::from('iquesters/demo')->update(['client_secret' => 'old-secret', 'app_name' => 'Old']);
        $admin = $this->makeUser('super-admin');

        $this->actingAs($admin)->put("/modules/config/{$module}", [
            'settings' => ['client_secret' => '', 'app_name' => 'New'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $stored = $this->storedRaw();
        $this->assertTrue(ConfSecret::isEncrypted($stored['client_secret']));
        $this->assertSame('old-secret', ConfSecret::decrypt($stored['client_secret']));
        $this->assertSame('New', $stored['app_name']);

        $this->actingAs($admin)->put("/modules/config/{$module}", [
            'settings' => ['client_secret' => 'rotated-secret'],
        ])->assertRedirect();

        $this->assertSame('rotated-secret', ConfSecret::decrypt($this->storedRaw()['client_secret']));
        $this->assertSame('rotated-secret', ConfProvider::from('iquesters/demo')->client_secret);
    }

    public function test_config_screen_is_admin_only(): void
    {
        $this->putJson('/modules/config/1', [])->assertUnauthorized();
        $this->actingAs($this->makeUser('user'))->put('/modules/config/1', [])->assertForbidden();
    }
}

class DemoSecretsConf extends BaseConf
{
    protected ?string $identifier = 'iquesters/demo';

    protected ?string $client_secret;
    protected ?string $access_token;
    protected string $phone_number_id;
    protected string $app_name;

    protected function prepareDefault(BaseConf $default_values)
    {
        $default_values->client_secret = '';
        $default_values->access_token = '';
        $default_values->phone_number_id = '';
        $default_values->app_name = 'Demo';
    }
}
