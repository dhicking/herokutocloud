<?php

use App\Http\Controllers\Api\CloudTokenController;
use App\Http\Controllers\Api\ConnectionsController;
use App\Http\Controllers\Api\HerokuAppsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('connections', ConnectionsController::class)->name('api.connections');

    Route::get('heroku/apps', [HerokuAppsController::class, 'index'])->name('api.heroku.apps');
    Route::get('heroku/apps/{app}', [HerokuAppsController::class, 'show'])->name('api.heroku.apps.show');

    Route::post('cloud/token', [CloudTokenController::class, 'store'])->name('api.cloud.token.store');
    Route::delete('cloud/token', [CloudTokenController::class, 'destroy'])->name('api.cloud.token.destroy');
});
