<?php

declare(strict_types=1);

// Replaces codewithkyrian/transformers' CharTrie (loaded via composer.json
// "autoload.files", so it is defined before the vendor class could be).
//
// Upstream (0.6.2 and main) implements commonPrefixSearch() with getChild(),
// which *creates* the missing child. Every tokenizer lookup therefore adds a
// node per character of the remaining input and never frees it, so the
// trie grows with every text embedded: importing ~50 varied Hebrew
// descriptions exhausted 512M, and Octane workers leaked on every query.
// This version only reads during search. push() and the results are unchanged.
// Remove once fixed upstream.

namespace Codewithkyrian\Transformers\DataStructures;

use Generator;

class CharTrie
{
    private CharTrieNode $root;

    public function __construct()
    {
        $this->root = CharTrieNode::default();
    }

    public function extend(array $texts): void
    {
        array_map([$this, 'push'], $texts);
    }

    public function push(string $text): void
    {
        $node = $this->root;
        foreach (mb_str_split($text) as $ch) {
            $node = $node->getChild($ch);
        }
        $node->isLeaf = true;
    }

    /** Yields every string in the trie that is a prefix of $text, shortest first. */
    public function commonPrefixSearch(string $text): Generator
    {
        $node = $this->root;
        $prefix = '';
        foreach (mb_str_split($text) as $ch) {
            $node = $node->children[$ch] ?? null;
            if ($node === null) {
                return;
            }
            $prefix .= $ch;
            if ($node->isLeaf) {
                yield $prefix;
            }
        }
    }
}
