<?php

namespace App\Console\Commands;

use App\Search\ProductSearch;
use Illuminate\Console\Command;

/**
 * Measures search quality against a labelled query set:
 *   [{"query": "שואב אבק", "expect": ["12", "40"]}, {"query": "גיטרה", "expect": []}]
 * `expect` lists external_ids that are a correct top hit; an empty list means
 * the right answer is "no results".
 */
class EvaluateSearch extends Command
{
    protected $signature = 'catalog:eval
        {path=data/eval.json : Labelled queries, relative to the project root}
        {--details : Print the top results of every query}
        {--set=* : Override a ranking setting for this run, e.g. --set=min_semantic=0.5}';

    protected $description = 'Score search relevance (hit@1, MRR, false positives) on labelled queries';

    public function handle(): int
    {
        foreach ($this->option('set') as $override) {
            [$key, $value] = explode('=', $override, 2) + [1 => null];
            config(["catalog.search.{$key}" => (float) $value]);
        }
        $search = $this->laravel->make(ProductSearch::class);

        $path = $this->argument('path');
        $cases = json_decode((string) @file_get_contents(str_starts_with($path, '/') ? $path : base_path($path)), true);
        if (! is_array($cases)) {
            $this->error("Cannot read labelled queries from {$path}");

            return self::FAILURE;
        }

        $positives = $hits = $reciprocal = $negatives = $falsePositives = 0;
        $rows = [];

        foreach ($cases as ['query' => $query, 'expect' => $expect]) {
            $results = $search->search($query);
            $ids = $results->pluck('external_id')->all();
            $top = $results->take(3)->map(fn ($p) => sprintf('%s (%.2f/%.2f)', mb_strimwidth($p->name, 0, 30, '…'), $p->margin, $p->lexical))->implode(' · ');

            if ($expect === []) {
                $negatives++;
                $ok = $ids === [];
                $falsePositives += (int) ! $ok;
                $verdict = $ok ? 'no results ✓' : 'should be empty ✗';
            } else {
                $positives++;
                $rank = collect($expect)->map(fn ($id) => array_search((string) $id, $ids, true))->filter(fn ($r) => $r !== false)->min();
                $hits += (int) ($rank === 0);
                $reciprocal += $rank === null ? 0 : 1 / ($rank + 1);
                $ok = $rank === 0;
                $verdict = $rank === null ? 'missing ✗' : ($rank === 0 ? '#1 ✓' : '#'.($rank + 1).' ~');
            }

            if (! $ok || $this->option('details')) {
                $rows[] = [$query, $verdict, count($ids), $top ?: '—'];
            }
        }

        $this->table(['query', 'verdict', 'results', 'top 3 (margin/lexical)'], $rows);
        $this->info(sprintf(
            '%s → hit@1 %d/%d (%.0f%%) · MRR %.2f · false positives %d/%d',
            implode(' ', $this->option('set')) ?: 'config defaults',
            $hits, $positives, $positives ? 100 * $hits / $positives : 0,
            $positives ? $reciprocal / $positives : 0, $falsePositives, $negatives,
        ));

        return self::SUCCESS;
    }
}
