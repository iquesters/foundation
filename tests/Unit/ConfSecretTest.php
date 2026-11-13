<?php

namespace Iquesters\Foundation\Tests\Unit;

use Iquesters\Foundation\Support\ConfSecret;
use Iquesters\Foundation\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ConfSecretTest extends TestCase
{
    public static function keys(): array
    {
        return [
            ['$SOCIAL_LOGIN~@O_AUTH_PROVIDERS~$GOOGLE~CLIENT_SECRET', true],
            ['$WHATSAPP_LOGIN~ACCESS_TOKEN', true],
            ['$RECAPTCHA~SECRET_KEY', true],
            ['smtp_password', true],
            ['OPENAI_API_KEY', true],
            ['$WHATSAPP_LOGIN~PHONE_NUMBER_ID', false],
            ['APP_NAME', false],
            ['$RECAPTCHA~SITE_KEY', false],
        ];
    }

    #[DataProvider('keys')]
    public function test_detects_sensitive_keys(string $key, bool $sensitive): void
    {
        $this->assertSame($sensitive, ConfSecret::isSensitive($key));
    }

    public function test_round_trips_and_marks_ciphertext(): void
    {
        $stored = ConfSecret::encrypt('s3cret-value');

        $this->assertStringStartsWith(ConfSecret::PREFIX, $stored);
        $this->assertStringNotContainsString('s3cret-value', $stored);
        $this->assertSame('s3cret-value', ConfSecret::decrypt($stored));
    }

    public function test_does_not_double_encrypt_or_encrypt_empty(): void
    {
        $stored = ConfSecret::encrypt('abc');

        $this->assertSame($stored, ConfSecret::encrypt($stored));
        $this->assertSame('', ConfSecret::encrypt(''));
        $this->assertSame('', ConfSecret::encrypt(null));
    }

    public function test_plain_legacy_values_pass_through_decrypt(): void
    {
        $this->assertSame('legacy-plain', ConfSecret::decrypt('legacy-plain'));
        $this->assertTrue(ConfSecret::decrypt(true));
    }
}
