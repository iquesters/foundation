<?php

namespace Iquesters\Foundation\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Iquesters\Foundation\Rules\MobileNumber;
use Iquesters\Foundation\Support\Phone;
use Iquesters\Foundation\Tests\TestCase;

/** Mobile numbers of every country (libphonenumber), as people type them. */
class MobileNumberTest extends TestCase
{
    public function test_a_mobile_number_is_checked_for_its_country(): void
    {
        // India: 10 digits starting 6–9; spaces, dashes and a leading 0 are fine.
        $this->assertSame('+91 98000 00001', Phone::format('+91', '098000-00001'));
        $this->assertFalse(Phone::isValid('+91', '12345'));
        $this->assertFalse(Phone::isValid('+91', '5800000001'));
        // Abroad: each country's own rules; a landline isn't a mobile.
        $this->assertSame('+44 7911 123456', Phone::format('+44', '07911 123456'));
        $this->assertSame('+971 55 123 4567', Phone::format('+971', '055 123 4567'));
        $this->assertFalse(Phone::isValid('+971', '04 123 4567'));
        $this->assertTrue(Phone::isValid('+1', '(604) 555-0123'));
        // Another country's number under this code, or letters: no.
        $this->assertFalse(Phone::isValid('+91', '+44 7911 123456'));
        $this->assertFalse(Phone::isValid('+91', 'call me'));

        // Every country for a list, the usual ones first; each code once for validation.
        $this->assertSame(['value' => '+91', 'label' => 'India (+91)'], Phone::options()[0]);
        $this->assertGreaterThan(200, count(Phone::options()));
        $this->assertContains('+880', Phone::codes());
        $this->assertSame('India', Phone::countryName('+91'));
    }

    public function test_the_rule_reads_the_code_next_to_the_number(): void
    {
        $rules = ['phone' => ['nullable', new MobileNumber], 'guests.*.phone' => ['nullable', new MobileNumber]];

        $ok = Validator::make(['phone_code' => '+91', 'phone' => '98000 00001', 'guests' => [['phone_code' => '+44', 'phone' => '07911 123456']]], $rules);
        $this->assertTrue($ok->passes());

        // The guest's own code: a UK number under India's code fails, with India's example.
        $bad = Validator::make(['phone_code' => '+91', 'phone' => '9800000001', 'guests' => [['phone_code' => '+91', 'phone' => '07911 123456']]], $rules);
        $this->assertTrue($bad->fails());
        $this->assertSame(['guests.0.phone'], array_keys($bad->errors()->toArray()));
        $this->assertStringStartsWith('Enter a valid India mobile number (e.g. ', $bad->errors()->first('guests.0.phone'));

        // Empty: left to "required" / "nullable".
        $this->assertTrue(Validator::make(['phone' => ''], $rules)->passes());
    }
}
