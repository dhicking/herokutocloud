<?php

use App\Http\Controllers\HerokuOAuthController;
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

Route::middleware(['auth'])->group(function () {
    Route::get('heroku/redirect', [HerokuOAuthController::class, 'redirect'])->name('heroku.redirect');
    Route::get('heroku/callback', [HerokuOAuthController::class, 'callback'])->name('heroku.callback');
    Route::delete('heroku/disconnect', [HerokuOAuthController::class, 'destroy'])->name('heroku.destroy');
});

require __DIR__.'/settings.php';
