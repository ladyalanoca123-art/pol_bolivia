<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\MascotaController;
use App\Http\Controllers\Admin\SolicitudAdopcionController as AdminSolicitudAdopcionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\SolicitudAdopcionController;
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

Route::middleware(['auth', 'role:ciudadano'])->group(function () {
    Route::get('/mascotas/{mascota}/solicitudes-adopcion/create', [SolicitudAdopcionController::class, 'create'])
        ->name('solicitudes-adopcion.create');

    Route::post('/mascotas/{mascota}/solicitudes-adopcion', [SolicitudAdopcionController::class, 'store'])
        ->name('solicitudes-adopcion.store');

    Route::get('/mis-solicitudes-adopcion', [SolicitudAdopcionController::class, 'index'])
        ->name('solicitudes-adopcion.index');

    Route::get('/mis-solicitudes-adopcion/{solicitud}', [SolicitudAdopcionController::class, 'show'])
        ->name('solicitudes-adopcion.show');
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

        Route::get('/mascotas/{mascota}', [MascotaController::class, 'show'])
            ->name('mascotas.show');

        Route::post('/mascotas/{mascota}/fotos', [MascotaController::class, 'storePhoto'])
            ->name('mascotas.fotos.store');

        Route::patch('/mascotas/{mascota}/fotos/{foto}/principal', [MascotaController::class, 'setPrincipalPhoto'])
            ->name('mascotas.fotos.principal');

        Route::delete('/mascotas/{mascota}/fotos/{foto}', [MascotaController::class, 'destroyPhoto'])
            ->name('mascotas.fotos.destroy');

        Route::get('/solicitudes-adopcion', [AdminSolicitudAdopcionController::class, 'index'])
            ->name('solicitudes-adopcion.index');

        Route::get('/solicitudes-adopcion/{solicitud}', [AdminSolicitudAdopcionController::class, 'show'])
            ->name('solicitudes-adopcion.show');
    });

require __DIR__.'/auth.php';
