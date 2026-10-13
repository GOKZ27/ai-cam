<?php

use App\Http\Controllers\AiCamController;
use Illuminate\Support\Facades\Route;

Route::get('/ai-cam', [AiCamController::class, 'index']);

Route::get('/api/ai-cam/health', [
    AiCamController::class, 'health',
]);

Route::post('/api/ai-cam/detect', [
    AiCamController::class, 'detect',
]);

Route::post('/api/ai-cam/describe', [
    AiCamController::class, 'describe',
]);
