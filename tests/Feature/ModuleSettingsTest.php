<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Iquesters\Foundation\Support\BaseConf;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Support\ConfSecret;
use Iquesters\Foundation\Support\Settings\SettingsStore;
use Iquesters\Foundation\Tests\TestCase;

class ModuleSettingsTest extends TestCase
{
    private const MODULE = 'iquesters/settings-demo';

    protected function setUp(): void
    {
        parent::setUp();
        ConfProvider::register(self::MODULE, DemoSettingsConf::class);
    }

    private function conf(): DemoSettingsConf
    {
        ConfProvider::forget(self::MODULE);

        return ConfProvider::from(self::MODULE);
    }

    private function storeRaw(array $settings): void
    {
        DB::table(SettingsStore::TABLE)->updateOrInsert(['module' => self::MODULE], ['settings' => json_encode($settings), 'updated_at' => now()]);
        SettingsStore::forget(self::MODULE);
    }

    public function test_defaults_come_from_the_settings_class(): void
    {
        $conf = $this->conf();

        $this->assertSame('classic', $conf->mode);
        $this->assertSame(7, $conf->tabs);
        $this->assertFalse($conf->enabled);
        $this->assertNull($conf->note);
        $this->assertSame(['email', 'profile'], $conf->scopes);
        $this->assertSame('bottom-right', $conf->toast->position);
        $this->assertSame('', $conf->providers['google']->client_secret);
    }

    public function test_stored_values_are_converted_to_the_declared_types(): void
    {
        $this->storeRaw(['tabs' => '9', 'enabled' => 'true', 'toast' => ['position' => 'top-left'], 'scopes' => '["email"]']);

        $conf = $this->conf();

        $this->assertSame(9, $conf->tabs);
        $this->assertTrue($conf->enabled);
        $this->assertSame('top-left', $conf->toast->position);
        $this->assertSame(['email'], $conf->scopes, 'lists are replaced, not merged');
    }

    public function test_invalid_stored_values_fall_back_to_the_default(): void
    {
        $this->storeRaw(['tabs' => 'many', 'mode' => 'weird', 'toast' => ['position' => 'nowhere']]);

        $conf = $this->conf();

        $this->assertSame(7, $conf->tabs, 'wrong type');
        $this->assertSame('classic', $conf->mode, 'breaks the in: rule');
        $this->assertSame('bottom-right', $conf->toast->position, 'nested rule');
    }

    public function test_update_stores_only_values_that_differ_from_the_defaults(): void
    {
        $this->conf()->update(['mode' => 'schema', 'tabs' => 7, 'toast' => ['position' => 'bottom-right', 'duration' => '5000']]);

        $stored = SettingsStore::get(self::MODULE);
        $this->assertSame(['mode' => 'schema', 'toast' => ['duration' => 5000]], $stored);
        $this->assertSame('schema', $this->conf()->mode);

        // Setting a value back to its default removes it from storage.
        $this->conf()->update(['mode' => 'classic']);
        $this->assertSame(['toast' => ['duration' => 5000]], SettingsStore::get(self::MODULE));
    }

    public function test_update_rejects_wrong_types_and_rule_violations(): void
    {
        try {
            $this->conf()->update(['tabs' => 'many', 'mode' => 'weird', 'toast' => ['position' => 'nowhere']]);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['tabs', 'mode', 'toast.position'], array_keys($e->errors()));
        }

        $this->assertSame([], SettingsStore::get(self::MODULE), 'nothing is saved when validation fails');
    }

    public function test_secrets_are_encrypted_at_rest_and_readable_in_code(): void
    {
        $this->conf()->update(['providers' => ['google' => ['client_secret' => 'top-secret']]]);

        $raw = json_decode((string) DB::table(SettingsStore::TABLE)->where('module', self::MODULE)->value('settings'), true);
        $this->assertTrue(ConfSecret::isEncrypted($raw['providers']['google']['client_secret']));
        $this->assertSame('top-secret', $this->conf()->providers['google']->client_secret);
    }

    public function test_isset_and_null_coalescing_see_the_real_value(): void
    {
        $conf = $this->conf();

        $this->assertTrue(isset($conf->mode));
        $this->assertSame('classic', $conf->mode ?? 'fallback');
        $this->assertFalse(isset($conf->note));
    }

    public function test_nested_settings_do_not_query_the_database(): void
    {
        DB::enableQueryLog();
        new DemoToastConf;
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_legacy_rows_are_converted_to_overrides(): void
    {
        $legacy = SettingsStore::fromLegacyRows([
            ['MODE', 'schema'],
            ['TABS', '7'],                                   // equals the default → dropped
            ['ENABLED', 'true'],
            ['IDENTIFIER', 'iquesters/settings-demo'],       // metadata → skipped
            ['$TOAST~POSITION', 'top-left'],
            ['$TOAST~IDENTIFIER', 'toast'],
            ['@PROVIDERS~$GOOGLE~CLIENT_SECRET', 'abc'],
            ['@SCOPES', '["email","profile"]'],              // equals the default → dropped
        ]);

        $this->assertSame(
            ['mode' => 'schema', 'enabled' => true, 'toast' => ['position' => 'top-left'], 'providers' => ['google' => ['client_secret' => 'abc']]],
            $this->conf()->overridesFrom($legacy)
        );
    }
}

class DemoSettingsConf extends BaseConf
{
    protected ?string $identifier = 'iquesters/settings-demo';

    protected string $mode;

    protected int $tabs;

    protected bool $enabled;

    protected ?string $note;

    protected array $scopes;

    protected DemoToastConf $toast;

    /** @var DemoProviderConf[] */
    protected array $providers;

    protected function prepareDefault(BaseConf $default_values)
    {
        $default_values->mode = 'classic';
        $default_values->tabs = 7;
        $default_values->enabled = false;
        $default_values->note = null;
        $default_values->scopes = ['email', 'profile'];
        $default_values->providers = ['google' => new DemoProviderConf];
    }

    protected function rules(): array
    {
        return ['mode' => 'in:classic,schema,both', 'tabs' => 'integer|between:1,20'];
    }
}

class DemoToastConf extends BaseConf
{
    protected ?string $identifier = 'toast';

    protected string $position;

    protected int $duration;

    protected function prepareDefault(BaseConf $default_values)
    {
        $default_values->position = 'bottom-right';
        $default_values->duration = 3000;
    }

    protected function rules(): array
    {
        return ['position' => 'in:bottom-right,top-left'];
    }
}

class DemoProviderConf extends BaseConf
{
    protected ?string $identifier = 'google';

    protected ?string $client_secret;

    protected function prepareDefault(BaseConf $default_values)
    {
        $default_values->client_secret = '';
    }
}
