<?php

namespace App\Models;

use App\Modules\CorePlatform\Contracts\PiiEncryptionServiceInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'status',
        'channel',
        'started_at',
        'ended_at',
        'duration_seconds',
        'caller_number',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'session_id', 'session_id');
    }

    public function transcripts()
    {
        return $this->hasMany(Transcript::class, 'session_id', 'session_id');
    }

    public function summaries()
    {
        return $this->hasMany(Summary::class, 'session_id', 'session_id');
    }

    /**
     * caller_number is encrypted at rest (ADR-035) — the DB column is
     * plaintext-shaped (string) but every value in it is a PiiEncryptionService
     * ciphertext, never the raw number.
     */
    public function setCallerNumberAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['caller_number'] = null;

            return;
        }

        $this->attributes['caller_number'] = app(PiiEncryptionServiceInterface::class)
            ->encrypt($this->tenant_id, $value);
    }

    public function getDecryptedCallerNumberAttribute(): ?string
    {
        if ($this->attributes['caller_number'] === null) {
            return null;
        }

        return app(PiiEncryptionServiceInterface::class)
            ->decrypt($this->tenant_id, $this->attributes['caller_number']);
    }
}
