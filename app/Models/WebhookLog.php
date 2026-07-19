<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $primaryKey = 'log_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['tenant_id', 'provider', 'event', 'payload_json'];

    protected function casts(): array
    {
        return ['payload_json' => 'array', 'created_at' => 'datetime'];
    }
}
