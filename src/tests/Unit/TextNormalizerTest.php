<?php

namespace Tests\Unit;

use App\Support\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_normalizes(string $input, string $expected): void
    {
        $this->assertSame($expected, TextNormalizer::normalize($input));
    }

    public static function cases(): array
    {
        return [
            'lowercases english' => ['iPhone 16 PRO', 'iphone 16 pro'],
            'strips niqqud' => ['שָׁלוֹם', 'שלום'],
            'gershayim to quote' => ['מק״ט', 'מק"ט'],
            'collapses whitespace' => ["  שואב \t  אבק\n", 'שואב אבק'],
            'mixed languages' => ['אייפון  iPhone', 'אייפון iphone'],
        ];
    }
}
