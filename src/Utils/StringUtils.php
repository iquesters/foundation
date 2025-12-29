<?php

namespace Iquesters\Foundation\Utils;

class StringUtils
{
    /**
     * Convert PascalCase or CamelCase to kebab-case
     * AbcXyzSeeder -> abc-xyz
     * UserProfileSeeder -> user-profile
     */
    public static function toKebabCase(string $string): string
    {
        // Convert PascalCase/CamelCase to kebab-case
        $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

        return $kebab;
    }

    /**
     * Convert kebab-case to PascalCase
     * abc-xyz -> AbcXyz
     */
    public static function toPascalCase(string $string): string
    {
        $pascal = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
        return $pascal;
    }
}
