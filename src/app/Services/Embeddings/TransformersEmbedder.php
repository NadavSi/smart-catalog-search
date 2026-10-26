<?php

namespace App\Services\Embeddings;

use App\Contracts\Embedder;
use Codewithkyrian\Transformers\Pipelines\Pipeline;

use function Codewithkyrian\Transformers\Pipelines\pipeline;

/**
 * Local sentence embeddings via TransformersPHP (ONNX Runtime over FFI).
 *
 * Loading the model takes seconds, so it is loaded lazily once and kept;
 * under Octane this object lives for the whole worker.
 */
class TransformersEmbedder implements Embedder
{
    private ?Pipeline $pipeline = null;

    public function __construct(
        private readonly string $model,
        private readonly string $cacheDir,
        private readonly string $pooling = 'mean',
        private readonly string $queryPrefix = '',
        private readonly string $documentPrefix = '',
        private readonly int $batchSize = 16,
    ) {}

    public function embedQuery(string $text): array
    {
        return $this->embed([$this->queryPrefix.$text])[0];
    }

    public function embedDocuments(array $texts): array
    {
        $vectors = [];
        foreach (array_chunk($texts, $this->batchSize) as $chunk) {
            array_push($vectors, ...$this->embed(array_map(fn ($t) => $this->documentPrefix.$t, $chunk)));
        }

        return $vectors;
    }

    /** Downloads the model files into the cache dir if they aren't there yet. */
    public function warm(): void
    {
        $this->pipeline();
    }

    private function embed(array $inputs): array
    {
        $vectors = ($this->pipeline())($inputs, pooling: $this->pooling);

        // The pipeline's own `normalize` option does not take effect in
        // TransformersPHP 0.6, so do the L2 normalization here.
        return array_map(self::normalize(...), $vectors);
    }

    private function pipeline(): Pipeline
    {
        return $this->pipeline ??= pipeline('feature-extraction', $this->model, cacheDir: $this->cacheDir);
    }

    private static function normalize(array $vector): array
    {
        // CLS pooling comes back with an extra [1 x dims] level.
        while (is_array($vector[0] ?? null)) {
            $vector = $vector[0];
        }

        $length = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vector))) ?: 1.0;

        return array_map(fn ($x) => $x / $length, $vector);
    }
}
