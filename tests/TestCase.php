<?php

namespace Tests;

use App\Modules\CorePlatform\Contracts\PiiEncryptionServiceInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The real implementation talks to GCP Secret Manager for
        // per-tenant keys — unreachable in CI. Reversible stand-in keeps
        // any test that touches Session.caller_number self-contained.
        $this->app->instance(PiiEncryptionServiceInterface::class, new class implements PiiEncryptionServiceInterface
        {
            public function encrypt(string $tenantId, string $plaintext): string
            {
                return 'test:'.base64_encode($plaintext);
            }

            public function decrypt(string $tenantId, string $payload): string
            {
                return base64_decode(explode(':', $payload, 2)[1]);
            }
        });
    }
}
