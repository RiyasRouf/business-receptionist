<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiProviderModel extends Model
{
    use HasUuids;

    protected $table = 'ai_provider_models';

    protected $primaryKey = 'model_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['provider_id', 'name', 'input_cost_per_1m', 'output_cost_per_1m'];

    protected function casts(): array
    {
        return [
            'input_cost_per_1m' => 'float',
            'output_cost_per_1m' => 'float',
        ];
    }

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id', 'provider_id');
    }
}
