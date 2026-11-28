<?php

namespace Iquesters\Foundation\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Mobile numbers for every country, checked with Google's libphonenumber (the
 * numbering plans of all countries, kept up to date with the library).
 *
 *     Phone::isValid('+91', '98000 00001')   // true: an Indian mobile
 *     Phone::isValid('+91', '12345')         // false
 *     Phone::format('+91', '098000 00001')   // "+91 98000 00001" (spaces, dashes, a leading 0 tidied)
 *     Phone::options()                       // [['value' => '+91', 'label' => 'India (+91)'], …] for a country code list
 *
 * Numbers are kept as "+{code} {number}", as format() writes them.
 */
class Phone
{
    /** Shown first in country lists (most members are here); then every other country A–Z. */
    public const FIRST = ['IN', 'AE', 'US', 'CA', 'GB', 'SA', 'QA', 'OM', 'KW', 'BH', 'SG', 'AU', 'BD', 'NP'];

    private static ?array $countries = null;

    /** @return array<string, array{0: string, 1: int}> region => [name, dialling code] */
    public static function countries(): array
    {
        return self::$countries ??= require __DIR__.'/phone-countries.php';
    }

    /**
     * Every country for a select: [value => "+91", label => "India (+91)"], the
     * usual ones first. Countries sharing a code (USA and Canada: +1) each have
     * their own line with the same value.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        $countries = self::countries();
        $first = array_intersect_key(array_replace(array_flip(self::FIRST), $countries), $countries);
        $rest = array_diff_key($countries, $first);
        $options = [];
        foreach ($first + $rest as [$name, $code]) {
            $options[] = ['value' => '+'.$code, 'label' => "{$name} (+{$code})"];
        }

        return $options;
    }

    /** @return string[] every dialling code ("+91", "+1"…), for validation */
    public static function codes(): array
    {
        return array_values(array_unique(array_map(fn ($c) => '+'.$c[1], self::countries())));
    }

    /** Is this a mobile number of that country code? Spaces, dashes, brackets and a leading 0 are fine. */
    public static function isValid(?string $code, ?string $number): bool
    {
        return self::parse($code, $number) !== null;
    }

    /** "+91 98000 00001", or null when it isn't a valid mobile number for that code. */
    public static function format(?string $code, ?string $number): ?string
    {
        $parsed = self::parse($code, $number);

        return $parsed ? PhoneNumberUtil::getInstance()->format($parsed, PhoneNumberFormat::INTERNATIONAL) : null;
    }

    /** The country a code belongs to, for messages ("India"; "United States" for +1). */
    public static function countryName(?string $code): ?string
    {
        $region = self::region($code);

        return $region ? (self::countries()[$region][0] ?? null) : null;
    }

    /** A mobile number of that country, as people write it ("081234 56789"), for messages. */
    public static function example(?string $code): ?string
    {
        $region = self::region($code);
        $util = PhoneNumberUtil::getInstance();
        $example = $region ? $util->getExampleNumberForType($region, PhoneNumberType::MOBILE) : null;

        return $example ? $util->format($example, PhoneNumberFormat::NATIONAL) : null;
    }

    private static function region(?string $code): ?string
    {
        $digits = (int) preg_replace('/\D/', '', (string) $code);
        $region = $digits ? PhoneNumberUtil::getInstance()->getRegionCodeForCountryCode($digits) : null;

        return $region && $region !== 'ZZ' ? $region : null;
    }

    private static function parse(?string $code, ?string $number): ?PhoneNumber
    {
        $number = trim((string) $number);
        $region = self::region($code);
        if ($number === '' || ! $region || preg_match('/[^0-9 ()+.\-]/', $number)) {
            return null;
        }
        $util = PhoneNumberUtil::getInstance();
        try {
            // Read as dialled in that country (a leading 0 is the country's trunk prefix).
            $parsed = $util->parse($number, $region);
        } catch (NumberParseException) {
            return null;
        }
        // The number must be of that code (not "+44…" typed under +91), valid, and able to be a mobile.
        $mobile = in_array($util->getNumberType($parsed), [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true);

        return $parsed->getCountryCode() === (int) preg_replace('/\D/', '', (string) $code) && $util->isValidNumber($parsed) && $mobile ? $parsed : null;
    }
}
