<?php

use App\Http\Controllers\Api\ResearchMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/research/metrics', [ResearchMetricsController::class, 'index'])
    ->name('api.research.metrics');
