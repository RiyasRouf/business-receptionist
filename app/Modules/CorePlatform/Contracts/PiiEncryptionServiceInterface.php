<?php

namespace App\Modules\CorePlatform\Contracts;

interface PiiEncryptionServiceInterface
{
    /**
     * Envelope-encrypt a plaintext value with the tenant's current PII key.
     * Output is self-describing (embeds the key version used) so decrypt()
     * can fetch the correct historical key after rotation.
     */
    public function encrypt(string $tenantId, string $plaintext): string;

    public function decrypt(string $tenantId, string $payload): string;
}
