<?php

use App\Http\Controllers\Api\ApiLogController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CronLogController;
use App\Http\Controllers\Api\SourceController;
use Illuminate\Support\Facades\Route;

Route::get('/articles',       [ArticleController::class, 'index']);
Route::get('/articles/{id}',  [ArticleController::class, 'show']);

Route::get('/sources',        [SourceController::class, 'index']);
Route::get('/sources/{id}',   [SourceController::class, 'show']);

Route::get('/cron-logs',      [CronLogController::class, 'index']);
Route::get('/cron-logs/{id}', [CronLogController::class, 'show']);

Route::get('/api-logs',       [ApiLogController::class, 'index']);
Route::get('/api-logs/{id}',  [ApiLogController::class, 'show']);
