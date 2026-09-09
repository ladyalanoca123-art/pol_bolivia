<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\MascotaController;
use App\Http\Controllers\Admin\IncidenciaController;
use App\Http\Controllers\Admin\PostulacionVoluntariadoController as AdminPostulacionVoluntariadoController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\SeguimientoAdopcionController as AdminSeguimientoAdopcionController;
use App\Http\Controllers\Admin\SolicitudAdopcionController as AdminSolicitudAdopcionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoluntarioController as AdminVoluntarioController;
use App\Http\Controllers\PostulacionVoluntariadoController;
use App\Http\Controllers\SolicitudAdopcionController;
use App\Http\Controllers\SeguimientoAdopcionController;
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

    Route::get('/postulaciones-voluntariado/create', [PostulacionVoluntariadoController::class, 'create'])->name('postulaciones-voluntariado.create');
    Route::post('/postulaciones-voluntariado', [PostulacionVoluntariadoController::class, 'store'])->name('postulaciones-voluntariado.store');
    Route::get('/mis-postulaciones-voluntariado', [PostulacionVoluntariadoController::class, 'index'])->name('postulaciones-voluntariado.index');
    Route::get('/mis-postulaciones-voluntariado/{postulacion}', [PostulacionVoluntariadoController::class, 'show'])->name('postulaciones-voluntariado.show');
});

Route::middleware(['auth', 'role:voluntario'])->group(function () {
    Route::get('/seguimientos', [SeguimientoAdopcionController::class, 'index'])->name('seguimientos.index');
    Route::get('/seguimientos/{seguimiento}', [SeguimientoAdopcionController::class, 'show'])->name('seguimientos.show');
    Route::post('/seguimientos/{seguimiento}/visitas', [SeguimientoAdopcionController::class, 'storeVisit'])->name('seguimientos.visitas.store');
    Route::patch('/seguimientos/{seguimiento}/visitas/{visita}/realizar', [SeguimientoAdopcionController::class, 'completeVisit'])->name('seguimientos.visitas.complete');
    Route::post('/seguimientos/{seguimiento}/incidencias', [SeguimientoAdopcionController::class, 'storeIncident'])->name('seguimientos.incidencias.store');
    Route::patch('/seguimientos/{seguimiento}/incidencias/{incidencia}/atender', [SeguimientoAdopcionController::class, 'attendIncident'])->name('seguimientos.incidencias.attend');
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

        Route::patch('/solicitudes-adopcion/{solicitud}/aceptar', [AdminSolicitudAdopcionController::class, 'accept'])
            ->name('solicitudes-adopcion.accept');

        Route::patch('/solicitudes-adopcion/{solicitud}/rechazar', [AdminSolicitudAdopcionController::class, 'reject'])
            ->name('solicitudes-adopcion.reject');

        Route::get('/seguimientos', [AdminSeguimientoAdopcionController::class, 'index'])->name('seguimientos.index');
        Route::get('/seguimientos/{seguimiento}', [AdminSeguimientoAdopcionController::class, 'show'])->name('seguimientos.show');
        Route::patch('/seguimientos/{seguimiento}/asignar', [AdminSeguimientoAdopcionController::class, 'assign'])->name('seguimientos.assign');
        Route::patch('/seguimientos/{seguimiento}/cerrar', [AdminSeguimientoAdopcionController::class, 'close'])->name('seguimientos.close');
        Route::patch('/incidencias/{incidencia}/en-proceso', [IncidenciaController::class, 'progress'])->name('incidencias.progress');
        Route::patch('/seguimientos/{seguimiento}/incidencias/{incidencia}/atender', [SeguimientoAdopcionController::class, 'attendIncident'])->name('seguimientos.incidencias.attend');

        Route::get('/postulaciones-voluntariado', [AdminPostulacionVoluntariadoController::class, 'index'])->name('postulaciones-voluntariado.index');
        Route::get('/postulaciones-voluntariado/{postulacion}', [AdminPostulacionVoluntariadoController::class, 'show'])->name('postulaciones-voluntariado.show');
        Route::patch('/postulaciones-voluntariado/{postulacion}/aprobar', [AdminPostulacionVoluntariadoController::class, 'approve'])->name('postulaciones-voluntariado.approve');
        Route::patch('/postulaciones-voluntariado/{postulacion}/rechazar', [AdminPostulacionVoluntariadoController::class, 'reject'])->name('postulaciones-voluntariado.reject');

        Route::get('/voluntarios', [AdminVoluntarioController::class, 'index'])->name('voluntarios.index');
        Route::get('/voluntarios/{voluntario}', [AdminVoluntarioController::class, 'show'])->name('voluntarios.show');
        Route::patch('/voluntarios/{voluntario}/desactivar', [AdminVoluntarioController::class, 'deactivate'])->name('voluntarios.deactivate');

        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    });

require __DIR__.'/auth.php';
