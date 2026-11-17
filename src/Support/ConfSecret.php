<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * Encryption-at-rest for secret config values stored in master_data_metas.
 *
 * A meta key is treated as secret when its name contains one of the
 * SENSITIVE_MARKERS (case-insensitive), e.g. GOOGLE_CLIENT_SECRET,
 * WHATSAPP_LOGIN_ACCESS_TOKEN. Secret values are stored as "enc:<payload>"
 * using the app key (Crypt::encryptString).
 */
class ConfSecret
{
    public const PREFIX = 'enc:';

    public const SENSITIVE_MARKERS = ['secret', 'token', 'password', 'api_key', 'apikey', 'private_key'];

    public static function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SENSITIVE_MARKERS as $marker) {
            if (str_contains($key, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /** Encrypts a value for storage. Empty and already-encrypted values pass through. */
    public static function encrypt(?string $value): string
    {
        $value ??= '';

        if ($value === '' || self::isEncrypted($value)) {
            return $value;
        }

        return self::PREFIX.Crypt::encryptString($value);
    }

    /** Decrypts a stored value. Plain (legacy) values pass through unchanged. */
    public static function decrypt(mixed $value): mixed
    {
        if (! self::isEncrypted($value)) {
            return $value;
        }

        return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
    }
}
