<?php

namespace App\Support;

/**
 * Puts Hebrew/English text into one canonical form, used both when
 * indexing products and when reading queries, so they compare equal.
 */
class TextNormalizer
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Hebrew niqqud and cantillation marks.
        $text = preg_replace('/[\x{0591}-\x{05C7}]/u', '', $text);

        // Hebrew geresh/gershayim and typographic quotes → plain ASCII.
        $text = strtr($text, ['״' => '"', '׳' => "'", '“' => '"', '”' => '"', '‘' => "'", '’' => "'"]);

        // Remove control characters and collapse whitespace.
        $text = preg_replace('/[\p{C}]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
