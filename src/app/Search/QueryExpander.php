<?php

namespace App\Search;

/**
 * Bridges Hebrew transliterations and Latin brand names ("אייפון" ↔ "iphone").
 * Embeddings link them only weakly and trigrams not at all, yet Israeli
 * shoppers type both forms.
 */
class QueryExpander
{
    /** Hebrew one-letter prefixes that attach to a word: ו ה ב כ ל מ ש. */
    private const HEBREW_PREFIX = '(?<![\p{L}\p{N}])([והבכלמש]?)';

    /** @param  array<string, string[]>  $synonyms  canonical (Latin) term => Hebrew spellings */
    public function __construct(private readonly array $synonyms) {}

    /**
     * The normalized query first, then one variant per spelling swap.
     *
     * @return string[]
     */
    public function variants(string $normalizedQuery): array
    {
        $variants = [$normalizedQuery];

        foreach ($this->synonyms as $latin => $hebrewForms) {
            foreach ($hebrewForms as $hebrew) {
                $hebrewPattern = '/'.self::HEBREW_PREFIX.preg_quote($hebrew, '/').'(?![\p{L}\p{N}])/u';
                if (preg_match($hebrewPattern, $normalizedQuery)) {
                    $variants[] = preg_replace($hebrewPattern, $latin, $normalizedQuery);
                }
            }

            $latinPattern = '/(?<![\p{L}\p{N}])'.preg_quote($latin, '/').'(?![\p{L}\p{N}])/u';
            if ($hebrewForms && preg_match($latinPattern, $normalizedQuery)) {
                $variants[] = preg_replace($latinPattern, $hebrewForms[0], $normalizedQuery);
            }
        }

        return array_values(array_unique($variants));
    }
}
