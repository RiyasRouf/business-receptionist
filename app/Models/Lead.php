<?php

namespace App\Models;

use App\Modules\CorePlatform\Contracts\PiiEncryptionServiceInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasUuids, SoftDeletes;

    public $timestamps = false;

    protected $primaryKey = 'lead_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'status',
        'fields_json',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'session_id');
    }

    /**
     * Field VALUES are individually encrypted (ADR-035) — the column
     * stays valid JSONB (queryable by key) rather than one opaque
     * ciphertext blob, matching kb_chunks.metadata_json's plain-JSONB
     * pattern for non-PII structure alongside PII field-level encryption.
     *
     * IMPORTANT: this mutator needs $this->tenant_id already set. Mass
     * assignment applies attributes in the order given to the input
     * array, not $fillable's declaration order — always put tenant_id
     * before fields_json in any create()/fill() call.
     */
    public function setFieldsJsonAttribute(?array $value): void
    {
        if ($value === null) {
            $this->attributes['fields_json'] = null;

            return;
        }

        $pii = app(PiiEncryptionServiceInterface::class);

        $encrypted = array_map(
            fn ($v) => $v === null ? null : $pii->encrypt($this->tenant_id, (string) $v),
            $value
        );

        $this->attributes['fields_json'] = json_encode($encrypted);
    }

    public function getFieldsJsonAttribute(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $pii = app(PiiEncryptionServiceInterface::class);
        $encrypted = json_decode($value, true);

        return array_map(
            fn ($v) => $v === null ? null : $pii->decrypt($this->tenant_id, $v),
            $encrypted
        );
    }
}
