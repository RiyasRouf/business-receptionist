<?php

namespace App\Modules\KnowledgeBase\Services;

use App\Models\TenantConfig;

class ChunkingService
{
    private const DEFAULT_CHUNK_SIZE = 800;

    private const DEFAULT_OVERLAP = 100;

    /**
     * Fixed-size chunking with overlap (AI_ARCHITECTURE.md §KB Pipeline).
     * Size/overlap are configurable per tenant via tenant_config.
     *
     * @return string[]
     */
    public function chunk(string $tenantId, string $text): array
    {
        $size = $this->tenantConfigInt($tenantId, 'kb.chunk_size', self::DEFAULT_CHUNK_SIZE);
        $overlap = $this->tenantConfigInt($tenantId, 'kb.chunk_overlap', self::DEFAULT_OVERLAP);

        $text = trim($text);

        if ($text === '') {
            return [];
        }

        if ($overlap >= $size) {
            $overlap = intdiv($size, 4);
        }

        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunk = mb_substr($text, $start, $size);
            $chunk = trim($chunk);

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $start += ($size - $overlap);
        }

        return $chunks;
    }

    private function tenantConfigInt(string $tenantId, string $key, int $default): int
    {
        $value = TenantConfig::where('tenant_id', $tenantId)->where('key', $key)->value('value');

        return $value !== null ? (int) $value : $default;
    }
}
