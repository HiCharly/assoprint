<?php

use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PrintJobController;
use Illuminate\Support\Facades\Route;

// Pas de page d'accueil publique : l'application est réservée aux membres du
// club, un visiteur est donc envoyé directement vers la connexion.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))
    ->name('home');

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
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('print', [PrintJobController::class, 'create'])->name('print.create');
    Route::post('print', [PrintJobController::class, 'store'])->name('print.store');

    Route::get('print/jobs', [PrintJobController::class, 'index'])->name('print.jobs');

    Route::get('print/jobs/{printJob}/document', [PrintJobController::class, 'document'])
        ->name('print.jobs.document');

    Route::get('print/jobs/{printJob}/duplicate', [PrintJobController::class, 'duplicateCreate'])
        ->name('print.jobs.duplicate.show');
    Route::post('print/jobs/{printJob}/duplicate', [PrintJobController::class, 'duplicate'])
        ->name('print.jobs.duplicate');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
