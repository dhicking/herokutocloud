<?php

use App\Http\Controllers\Api\ConnectionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('connections', ConnectionsController::class)->name('api.connections');
});
