<?php

use App\Http\Controllers\Auth\CloudAuthController;
use App\Http\Controllers\Auth\HerokuAuthController;
use App\Http\Controllers\Import\HerokuApiController;
use App\Http\Controllers\Import\ImportApiController;
use App\Http\Controllers\Import\ImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/auth/heroku', [HerokuAuthController::class, 'redirect'])->name('auth.heroku.redirect');
Route::get('/auth/heroku/callback', [HerokuAuthController::class, 'callback'])->name('auth.heroku.callback');
Route::post('/auth/cloud/verify', [CloudAuthController::class, 'verify'])->name('auth.cloud.verify');

Route::get('/import', [ImportController::class, 'connect'])->name('import.connect');
Route::middleware('import.authenticated')->group(function () {
    Route::get('/import/configure', [ImportController::class, 'configure'])->name('import.configure');
    Route::get('/import/deploy', [ImportController::class, 'deploy'])->name('import.deploy');
    Route::get('/api/heroku/apps', [HerokuApiController::class, 'listApps'])->name('api.heroku.apps');
    Route::get('/api/heroku/apps/{appId}/resources', [HerokuApiController::class, 'getResources'])->name('api.heroku.apps.resources');
    Route::post('/api/import/plan', [ImportApiController::class, 'generatePlan'])->name('api.import.plan');
    Route::post('/api/import/execute', [ImportApiController::class, 'executePlan'])->name('api.import.execute');
});

require __DIR__.'/settings.php';
