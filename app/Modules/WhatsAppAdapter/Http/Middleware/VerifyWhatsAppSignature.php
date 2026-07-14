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
        $signature = $request->header('X-Hub-Signature-256', '');

        if (! $this->adapter->verifySignature($request->getContent(), $signature)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        return $next($request);
    }
}
