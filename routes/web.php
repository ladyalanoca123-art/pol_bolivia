<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\MascotaController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

Route::middleware(['auth', 'role:administrador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::view('/panel', 'admin.dashboard')
            ->name('dashboard');

        Route::get('/usuarios', [UserController::class, 'index'])
            ->name('usuarios.index');

        Route::patch('/usuarios/{user}/estado', [UserController::class, 'updateStatus'])
            ->name('usuarios.estado.update');

        Route::get('/mascotas', [MascotaController::class, 'index'])
            ->name('mascotas.index');

        Route::get('/mascotas/create', [MascotaController::class, 'create'])
            ->name('mascotas.create');

        Route::post('/mascotas', [MascotaController::class, 'store'])
            ->name('mascotas.store');
    });

require __DIR__.'/auth.php';
