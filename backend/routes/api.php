<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChunkController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentProcessingController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectExportController;
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

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::get('/strategies', [StrategyController::class, 'index']);

        Route::apiResource('projects', ProjectController::class);
        Route::get('/projects/{project}/documents', [DocumentController::class, 'index']);
        Route::post('/projects/{project}/documents', [DocumentController::class, 'store']);
        Route::get('/projects/{project}/export', [ProjectExportController::class, 'show']);

        Route::get('/documents/{document}', [DocumentController::class, 'show']);
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
        Route::post('/documents/{document}/process', [DocumentProcessingController::class, 'store']);
        Route::get('/documents/{document}/chunks', [ChunkController::class, 'index']);
    });
});
