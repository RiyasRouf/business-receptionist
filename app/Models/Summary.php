<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Summary extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $primaryKey = 'summary_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'content',
        'action_items_json',
    ];

    protected function casts(): array
    {
        return [
            'action_items_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }
}
