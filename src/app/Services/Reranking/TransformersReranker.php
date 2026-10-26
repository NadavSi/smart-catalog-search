<?php

namespace App\Services\Reranking;

use App\Contracts\Reranker;
use Codewithkyrian\Transformers\Models\Auto\AutoModel;
use Codewithkyrian\Transformers\Models\Pretrained\PretrainedModel;
use Codewithkyrian\Transformers\PreTrainedTokenizers\AutoTokenizer;
use Codewithkyrian\Transformers\PreTrainedTokenizers\PreTrainedTokenizer;

/**
 * Local cross-encoder (ONNX via TransformersPHP), e.g. bge-reranker-v2-m3.
 *
 * TransformersPHP 0.6 has no text-classification mapping for xlm-roberta, so
 * this skips the pipeline: AutoModel falls back to the base class, which runs
 * the whole ONNX graph (classifier head included) and returns its `logits`.
 * Loaded lazily and kept, like the embedder.
 */
class TransformersReranker implements Reranker
{
    private ?PreTrainedTokenizer $tokenizer = null;

    private ?PretrainedModel $model = null;

    public function __construct(
        private readonly string $modelName,
        private readonly string $cacheDir,
        private readonly int $maxLength = 192,
        private readonly int $batchSize = 8,
    ) {}

    public function score(string $query, array $documents): array
    {
        $this->warm();
        $scores = [];
        foreach (array_chunk($documents, $this->batchSize) as $chunk) {
            $inputs = ($this->tokenizer)(
                array_fill(0, count($chunk), $query),
                textPair: $chunk,
                padding: true,
                truncation: true,
                maxLength: $this->maxLength,
            );
            // logits: [batch x 1]
            foreach (($this->model)($inputs)['logits']->toArray() as $row) {
                $scores[] = (float) (is_array($row) ? $row[0] : $row);
            }
        }

        return $scores;
    }

    /** Downloads the model files into the cache dir if they aren't there yet, and loads them. */
    public function warm(): void
    {
        $this->tokenizer ??= AutoTokenizer::fromPretrained($this->modelName, cacheDir: $this->cacheDir);
        $this->model ??= AutoModel::fromPretrained($this->modelName, quantized: true, cacheDir: $this->cacheDir);
    }
}
