<?php

namespace App\Modules\KnowledgeBase\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * Embed a single chunk of text.
     *
     * @return float[] Vector of length dimension()
     */
    public function embed(string $text): array;

    public function modelId(): string;

    public function dimension(): int;
}
