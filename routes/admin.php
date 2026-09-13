<?php

use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'password.chosen', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');

        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');

        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('throttle:10,1')
            ->name('users.reset-password');

        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

        // scopeBindings : la tâche doit appartenir au membre de l'URL, sans quoi
        // un identifiant glissé à la main permettrait de relancer la tâche d'un
        // autre membre depuis la fiche de celui-ci.
        Route::get('users/{user}/jobs/{printJob}/relaunch', [UserController::class, 'relaunchCreate'])
            ->scopeBindings()
            ->name('users.jobs.relaunch.show');

        Route::post('users/{user}/jobs/{printJob}/relaunch', [UserController::class, 'relaunch'])
            ->scopeBindings()
            ->name('users.jobs.relaunch');

        Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    });
