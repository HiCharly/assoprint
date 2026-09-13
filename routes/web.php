<?php

use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Hors du groupe « password.chosen » : c'est précisément la page vers laquelle
// ce middleware redirige tant que le mot de passe temporaire n'a pas été changé.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('password/change', [ForcedPasswordChangeController::class, 'edit'])
        ->name('password.change');

    Route::post('password/change', [ForcedPasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change.update');
});

Route::middleware(['auth', 'active', 'password.chosen'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
