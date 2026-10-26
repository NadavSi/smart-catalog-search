<?php

namespace Tests\Unit;

use Codewithkyrian\Transformers\DataStructures\CharTrie;
use Codewithkyrian\Transformers\DataStructures\CharTrieNode;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/** Guards the override in overrides/CharTrie.php (the tokenizer's vocabulary trie). */
class CharTrieTest extends TestCase
{
    public function test_override_is_the_class_in_use(): void
    {
        $file = (new \ReflectionClass(CharTrie::class))->getFileName();

        $this->assertStringEndsWith('overrides/CharTrie.php', $file);
    }

    public function test_finds_all_prefixes_shortest_first(): void
    {
        $trie = new CharTrie;
        $trie->extend(['▁ש', '▁שו', '▁שואב', 'אבק']);

        $this->assertSame(['▁ש', '▁שו', '▁שואב'], iterator_to_array($trie->commonPrefixSearch('▁שואב אבק'), false));
        $this->assertSame([], iterator_to_array($trie->commonPrefixSearch('xyz'), false));
    }

    public function test_search_does_not_grow_the_trie(): void
    {
        $trie = new CharTrie;
        $trie->extend(['▁ש', '▁שו']);
        $before = $this->countNodes($trie);

        foreach (['▁שואב אבק רובוטי', 'מטהג אוויר חם', 'completely unseen text'] as $text) {
            iterator_to_array($trie->commonPrefixSearch($text));
        }

        $this->assertSame($before, $this->countNodes($trie));
    }

    private function countNodes(CharTrie $trie): int
    {
        $count = function (CharTrieNode $node) use (&$count): int {
            return 1 + array_sum(array_map($count, $node->children));
        };

        return $count((new ReflectionProperty($trie, 'root'))->getValue($trie));
    }
}
