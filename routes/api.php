<?php

use App\Models\User;
use App\Modules\CorePlatform\Http\Controllers\AuthController;
use App\Modules\CorePlatform\Http\Controllers\ReadinessController;
use App\Modules\KnowledgeBase\Http\Controllers\KnowledgeBaseController;
use App\Modules\LeadCapture\Http\Controllers\LeadController;
use App\Modules\Media\Http\Controllers\TranscriptController;
use App\Modules\WhatsAppAdapter\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/ready', [ReadinessController::class, 'check']);

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Meta calls these directly — no JWT, HMAC signature is the auth
    // mechanism (ADR-021). voice_webhook-equivalent system role.
    Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])
        ->middleware('whatsapp.signature');

    // Signed URL is its own time-limited auth (ADR-064) — deliberately
    // outside jwt.auth so a link can be shared/opened without a fresh
    // token, but the 'signed' middleware rejects any tampered/expired URL.
    Route::get('/transcripts/{transcript}/download', [TranscriptController::class, 'download'])
        ->middleware('signed')
        ->name('transcripts.download');

    Route::middleware('jwt.auth')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware(['tenant.resolve', 'role:'.User::ROLE_TENANT_ADMIN.','.User::ROLE_STAFF])
            ->group(function () {
                Route::get('/me', fn (\Illuminate\Http\Request $request) => response()->json([
                    'user_id' => $request->attributes->get('auth_user_id'),
                    'tenant_id' => $request->attributes->get('auth_tenant_id'),
                    'role' => $request->attributes->get('auth_role'),
                ]));

                Route::get('/kb/documents', [KnowledgeBaseController::class, 'index']);
                Route::post('/kb/documents', [KnowledgeBaseController::class, 'store']);
                Route::delete('/kb/documents/{documentId}', [KnowledgeBaseController::class, 'destroy']);
                Route::post('/kb/search', [KnowledgeBaseController::class, 'search']);

                Route::get('/leads', [LeadController::class, 'index']);
                Route::get('/leads/{leadId}', [LeadController::class, 'show']);
                Route::patch('/leads/{leadId}/status', [LeadController::class, 'updateStatus']);
            });
    });
});
