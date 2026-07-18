<?php

use App\Modules\CorePlatform\Http\Middleware\AddTraceId;
use App\Modules\CorePlatform\Http\Middleware\EnforcePermission;
use App\Modules\CorePlatform\Http\Middleware\JwtAuthenticate;
use App\Modules\CorePlatform\Http\Middleware\RequireRole;
use App\Modules\CorePlatform\Http\Middleware\ResolveTenant;
use App\Modules\OutboxRelay\Console\ConsumePostCallEventsCommand;
use App\Modules\OutboxRelay\Console\RelayOutboxCommand;
use App\Modules\WhatsAppAdapter\Http\Middleware\VerifyWhatsAppSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        RelayOutboxCommand::class,
        ConsumePostCallEventsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt.auth' => JwtAuthenticate::class,
            'tenant.resolve' => ResolveTenant::class,
            'role' => RequireRole::class,
            'permission' => EnforcePermission::class,
            'whatsapp.signature' => VerifyWhatsAppSignature::class,
        ]);

        $middleware->append(AddTraceId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API-only backend — always render JSON errors, regardless of the
        // client's Accept header (a bare curl/mobile POST has none, which
        // would otherwise make Laravel redirect validation failures to a
        // nonexistent web login route instead of returning 422 JSON).
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*'));
    })->create();
