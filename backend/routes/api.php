<?php

use App\Http\Controllers\Api\App\ConversationController as AppConversationController;
use App\Http\Controllers\Api\App\PublicationController as AppPublicationController;
use App\Http\Controllers\Api\App\QueryController as AppQueryController;
use App\Http\Controllers\Api\App\SessionController as AppSessionController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChunkController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentProcessingController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectCredentialController;
use App\Http\Controllers\Api\V1\ProjectExportController;
use App\Http\Controllers\Api\V1\PublicationController;
use App\Http\Controllers\Api\V1\QueryController;
use App\Http\Controllers\Api\V1\StrategyController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'backend',
        'env' => app()->environment(),
    ]);
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:login');

    // Session routes work for any authenticated platform account, including
    // admins who have no organization (so no `tenant` middleware here).
    // `platform-account` keeps employee (AppUser) tokens out.
    Route::middleware(['auth:sanctum', 'platform-account'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
    });

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('/strategies', [StrategyController::class, 'index']);

        Route::apiResource('projects', ProjectController::class);
        Route::get('/projects/{project}/documents', [DocumentController::class, 'index']);
        Route::post('/projects/{project}/documents', [DocumentController::class, 'store']);
        Route::get('/projects/{project}/export', [ProjectExportController::class, 'show']);

        Route::get('/documents/{document}', [DocumentController::class, 'show']);
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
        Route::post('/documents/{document}/process', [DocumentProcessingController::class, 'store']);
        Route::get('/documents/{document}/chunks', [ChunkController::class, 'index']);

        Route::get('/projects/{project}/credentials', [ProjectCredentialController::class, 'show']);
        Route::post('/projects/{project}/credentials', [ProjectCredentialController::class, 'store']);
        Route::delete('/projects/{project}/credentials', [ProjectCredentialController::class, 'destroy']);

        Route::post('/projects/{project}/query', [QueryController::class, 'store']);
        Route::get('/projects/{project}/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy']);

        Route::get('/projects/{project}/publication', [PublicationController::class, 'show']);
        Route::put('/projects/{project}/publication', [PublicationController::class, 'update']);

        Route::get('/projects/{project}/publication/employees', [EmployeeController::class, 'index']);
        Route::post('/projects/{project}/publication/employees', [EmployeeController::class, 'store']);
        Route::patch('/projects/{project}/publication/employees/{employee}', [EmployeeController::class, 'update']);
        Route::delete('/projects/{project}/publication/employees/{employee}', [EmployeeController::class, 'destroy']);
        Route::post('/projects/{project}/publication/employees/{employee}/reset-code', [EmployeeController::class, 'resetCode']);
    });

    // Platform administration — operates across all tenants, so no `tenant` middleware.
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::get('/stats', [AdminDashboardController::class, 'index']);
        Route::get('/settings', [AdminDashboardController::class, 'settings']);

        Route::get('/organizations', [AdminOrganizationController::class, 'index']);
        Route::post('/organizations', [AdminOrganizationController::class, 'store']);
        Route::patch('/organizations/{organization}', [AdminOrganizationController::class, 'update']);
        Route::patch('/organizations/{organization}/status', [AdminOrganizationController::class, 'updateStatus']);
    });
});

// Employee query app — public slug, no tenant context, its own auth guard.
Route::prefix('app')->group(function () {
    Route::get('/{slug}', [AppPublicationController::class, 'show']);
    Route::post('/{slug}/session', [AppSessionController::class, 'store'])->middleware('throttle:app-session');

    Route::middleware(['auth:sanctum', 'app-user'])->group(function () {
        Route::delete('/{slug}/session', [AppSessionController::class, 'destroy']);
        Route::post('/{slug}/query', [AppQueryController::class, 'store']);
        Route::get('/{slug}/conversations', [AppConversationController::class, 'index']);
        Route::get('/{slug}/conversations/{conversation}', [AppConversationController::class, 'show']);
    });
});
