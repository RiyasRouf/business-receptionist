<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    use HasUuids;

    protected $primaryKey = 'provider_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'api_key', 'base_url', 'status'];

    protected $hidden = ['api_key'];

    public function models()
    {
        return $this->hasMany(AiProviderModel::class, 'provider_id', 'provider_id');
    }
}
