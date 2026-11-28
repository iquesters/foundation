<?php

namespace Iquesters\Foundation\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Iquesters\Foundation\Support\Phone;

/**
 * A mobile number valid for the country code chosen next to it (every
 * country, see Phone). The code is read from the sibling field: "phone" →
 * "phone_code", "guests.2.phone" → "guests.2.phone_code".
 *
 *     'phone' => ['nullable', new MobileNumber()],
 *     'mobile' => ['required', new MobileNumber('mobile_country')],
 */
class MobileNumber implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(private ?string $codeField = null, private string $defaultCode = '+91') {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return; // required or not: the other rules say
        }
        $code = (string) (data_get($this->data, $this->codeKey($attribute)) ?: $this->defaultCode);
        if (Phone::isValid($code, (string) $value)) {
            return;
        }
        $country = Phone::countryName($code);
        $example = Phone::example($code);
        $fail(trim(($country ? "Enter a valid {$country} mobile number" : 'Enter a valid mobile number').($example ? " (e.g. {$example})" : '').', without the country code.'));
    }

    private function codeKey(string $attribute): string
    {
        if ($this->codeField !== null) {
            // A relative name for a field in a list: "guests.2.phone" with "phone_code" → "guests.2.phone_code".
            return str_contains($attribute, '.') && ! str_contains($this->codeField, '.')
                ? substr($attribute, 0, strrpos($attribute, '.') + 1).$this->codeField
                : $this->codeField;
        }
        $last = strrpos($attribute, '.');
        $name = $last === false ? $attribute : substr($attribute, $last + 1);

        return ($last === false ? '' : substr($attribute, 0, $last + 1)).$name.'_code';
    }
}
