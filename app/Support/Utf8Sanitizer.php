<?php

namespace App\Support;

class Utf8Sanitizer
{
    /**
     * Recursively sanitize strings in arrays for JSON/database storage.
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = self::sanitize($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return self::sanitizeString($value);
        }

        return $value;
    }

    /**
     * Ensure a string is valid UTF-8, preserving Latin-1 accents when possible.
     */
    public static function sanitizeString(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
