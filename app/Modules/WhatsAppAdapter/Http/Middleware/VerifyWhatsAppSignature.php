<?php

namespace App\Modules\WhatsAppAdapter\Http\Middleware;

use App\Modules\WhatsAppAdapter\Contracts\MessagingAdapterInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HMAC validation as first middleware — request rejected before any
 * processing if invalid (ADR-021).
 */
class VerifyWhatsAppSignature
{
    public function __construct(private readonly MessagingAdapterInterface $adapter) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 360dialog has no HMAC-over-body scheme — it authenticates
        // webhooks via a static Basic Auth Authorization header instead
        // of Meta's X-Hub-Signature-256. Which header carries the
        // "signature" depends on the active provider (MESSAGING_PROVIDER).
        $header = config('services.messaging_provider') === '360dialog'
            ? $request->header('Authorization', '')
            : $request->header('X-Hub-Signature-256', '');

        if (! $this->adapter->verifySignature($request->getContent(), $header)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        return $next($request);
    }
}
