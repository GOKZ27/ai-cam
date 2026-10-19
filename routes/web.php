<?php

use App\Http\Controllers\AiCamController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ai-cam');

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

Route::post('/api/ai-cam/annotate', [
    AiCamController::class, 'annotate',
]);

Route::get('/api/ai-cam/dataset', [
    AiCamController::class, 'dataset',
]);
