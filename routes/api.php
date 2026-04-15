<?php

use Illuminate\Support\Facades\Route;
use Platform\Datawarehouse\Http\Controllers\IngestController;

/**
 * Datawarehouse API Routes
 *
 * The ingest endpoint uses token-based auth (no session/API key required).
 */
Route::post('/ingest/{token}', IngestController::class)->name('datawarehouse.api.ingest');
