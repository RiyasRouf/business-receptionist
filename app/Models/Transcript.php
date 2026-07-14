<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transcript extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $primaryKey = 'transcript_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'content',
        'storage_path',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }
}
