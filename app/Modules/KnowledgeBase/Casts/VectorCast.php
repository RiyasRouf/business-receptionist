<?php

namespace App\Modules\KnowledgeBase\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * pgvector's wire format is a literal string like "[0.1,0.2,...]".
 * Eloquent has no native vector type, so this cast handles both
 * directions explicitly.
 *
 * @implements CastsAttributes<array<float>|null, array<float>|null>
 */
class VectorCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        return array_map('floatval', explode(',', trim($value, '[]')));
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return '['.implode(',', array_map('floatval', $value)).']';
    }
}
