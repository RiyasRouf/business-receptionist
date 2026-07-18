<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantIntegration extends Model
{
    use HasUuids;

    protected $primaryKey = 'integration_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'voice_provider', 'voice_account_sid', 'voice_auth_token', 'voice_phone_number',
        'voice_status', 'call_forwarding_type', 'business_hours', 'fallback_message',
        'whatsapp_number', 'whatsapp_display_name', 'whatsapp_phone_number_id', 'whatsapp_token',
        'whatsapp_greeting', 'whatsapp_status',
    ];

    protected $hidden = ['voice_auth_token', 'whatsapp_token'];
}
