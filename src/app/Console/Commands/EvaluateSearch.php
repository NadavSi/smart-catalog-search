<?php

namespace App\Console\Commands;

use App\Contracts\Reranker;
use App\Search\ProductSearch;
use App\Services\Reranking\CachingReranker;
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
        {--set=* : Override a ranking setting for this run, e.g. --set=min_margin=0.1}
        {--sweep= : Run once per value, e.g. --sweep=rerank_min=-5,-4,-3 (re-ranker scores are reused between runs)}';

    protected $description = 'Score search relevance (hit@1, MRR, false positives) on labelled queries';

    public function handle(): int
    {
        $path = $this->argument('path');
        $cases = json_decode((string) @file_get_contents(str_starts_with($path, '/') ? $path : base_path($path)), true);
        if (! is_array($cases)) {
            $this->error("Cannot read labelled queries from {$path}");

            return self::FAILURE;
        }

        $overrides = array_map(fn ($o) => explode('=', $o, 2) + [1 => null], $this->option('set'));
        $runs = [[]];
        if ($sweep = $this->option('sweep')) {
            [$key, $values] = explode('=', $sweep, 2) + [1 => ''];
            $runs = array_map(fn ($v) => [[$key, $v]], explode(',', $values));
            // Every run scores the same pairs; only the thresholds change.
            $this->laravel->extend(Reranker::class, fn (Reranker $r) => new CachingReranker($r));
        }

        foreach ($runs as $run) {
            $settings = [...$overrides, ...$run];
            foreach ($settings as [$key, $value]) {
                config(["catalog.search.{$key}" => (float) $value]);
            }
            $this->evaluate($cases, $this->laravel->make(ProductSearch::class), $sweep === null,
                implode(' ', array_map(fn ($s) => implode('=', $s), $settings)) ?: 'config defaults');
        }

        return self::SUCCESS;
    }

    private function evaluate(array $cases, ProductSearch $search, bool $table, string $label): void
    {
        $positives = $hits = $reciprocal = $negatives = $falsePositives = $missing = 0;
        $rows = [];
        $started = microtime(true);

        foreach ($cases as ['query' => $query, 'expect' => $expect]) {
            $results = $search->search($query);
            $ids = $results->pluck('external_id')->all();
            $top = $results->take(3)->map(fn ($p) => sprintf(
                isset($p->rerank) ? '%s (%.2f/%.2f/%.1f)' : '%s (%.2f/%.2f)',
                mb_strimwidth($p->name, 0, 30, '…'), $p->margin, $p->lexical, $p->rerank ?? null,
            ))->implode(' · ');

            if ($expect === []) {
                $negatives++;
                $ok = $ids === [];
                $falsePositives += (int) ! $ok;
                $verdict = $ok ? 'no results ✓' : 'should be empty ✗';
            } else {
                $positives++;
                $rank = collect($expect)->map(fn ($id) => array_search((string) $id, $ids, true))->filter(fn ($r) => $r !== false)->min();
                $hits += (int) ($rank === 0);
                $missing += (int) ($rank === null);
                $reciprocal += $rank === null ? 0 : 1 / ($rank + 1);
                $ok = $rank === 0;
                $verdict = $rank === null ? 'missing ✗' : ($rank === 0 ? '#1 ✓' : '#'.($rank + 1).' ~');
            }

            if (! $ok || $this->option('details')) {
                $rows[] = [$query, $verdict, count($ids), $top ?: '—'];
            }
        }

        if ($table) {
            $this->table(['query', 'verdict', 'results', 'top 3 (margin/lexical[/rerank])'], $rows);
        }
        $this->info(sprintf(
            '%s → hit@1 %d/%d (%.0f%%) · MRR %.2f · missing %d · false positives %d/%d · %.0fms/query',
            $label, $hits, $positives, $positives ? 100 * $hits / $positives : 0,
            $positives ? $reciprocal / $positives : 0, $missing, $falsePositives, $negatives,
            1000 * (microtime(true) - $started) / max(1, count($cases)),
        ));
    }
}
