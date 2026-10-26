<?php

namespace Tests\Unit;

use App\Search\QueryExpander;
use PHPUnit\Framework\TestCase;

class QueryExpanderTest extends TestCase
{
    private QueryExpander $expander;

    protected function setUp(): void
    {
        $this->expander = new QueryExpander(['iphone' => ['אייפון', 'איפון'], 'lg' => ['אל ג\'י']]);
    }

    public function test_hebrew_spelling_adds_latin_variant(): void
    {
        $this->assertSame(['אייפון 16', 'iphone 16'], $this->expander->variants('אייפון 16'));
        $this->assertSame(['איפון', 'iphone'], $this->expander->variants('איפון'));
    }

    public function test_latin_name_adds_hebrew_variant(): void
    {
        $this->assertSame(['iphone 16 pro', 'אייפון 16 pro'], $this->expander->variants('iphone 16 pro'));
    }

    public function test_handles_hebrew_prefix_letters(): void
    {
        $this->assertSame(['כיסוי לאייפון', 'כיסוי iphone'], $this->expander->variants('כיסוי לאייפון'));
    }

    public function test_matches_whole_words_only(): void
    {
        $this->assertSame(['iphones'], $this->expander->variants('iphones'));
        $this->assertSame(['global'], $this->expander->variants('global'));
    }

    public function test_multi_word_spelling(): void
    {
        $this->assertSame(["מקרר אל ג'י", 'מקרר lg'], $this->expander->variants("מקרר אל ג'י"));
    }
}
