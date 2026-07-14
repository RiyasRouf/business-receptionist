<?php

use App\Models\User;
use App\Modules\CorePlatform\Http\Controllers\AuthController;
use App\Modules\KnowledgeBase\Http\Controllers\KnowledgeBaseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

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
            });
    });
});
