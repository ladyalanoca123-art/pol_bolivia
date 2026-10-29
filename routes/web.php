<?php

use App\Http\Controllers\AccesoSolicitudAdopcionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CatalogoMascotaController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\MiEspacioController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\MascotaController;
use App\Http\Controllers\Admin\IncidenciaController;
use App\Http\Controllers\Admin\PostulacionVoluntariadoController as AdminPostulacionVoluntariadoController;
use App\Http\Controllers\Admin\PanelPrincipalController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\SeguimientoAdopcionController as AdminSeguimientoAdopcionController;
use App\Http\Controllers\Admin\SolicitudAdopcionController as AdminSolicitudAdopcionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoluntarioController as AdminVoluntarioController;
use App\Http\Controllers\PostulacionVoluntariadoController;
use App\Http\Controllers\SolicitudAdopcionController;
use App\Http\Controllers\SeguimientoAdopcionController;
use Illuminate\Support\Facades\Route;

// Páginas públicas: máximo 60 peticiones por minuto por IP.
Route::middleware('throttle:publico')->group(function () {
    Route::get('/', [CatalogoMascotaController::class, 'home'])->name('home');
    Route::get('/mascotas', [CatalogoMascotaController::class, 'index'])->name('mascotas.catalogo');
    Route::get('/mascotas/{mascota}', [CatalogoMascotaController::class, 'show'])->name('mascotas.public.show');

    // Desde el aviso "Para adoptar necesitas una cuenta" (solo visitantes): guarda como destino la
    // solicitud de esa mascota y lleva al registro o al inicio de sesión.
    Route::get('/mascotas/{mascota}/solicitar/{accion}', AccesoSolicitudAdopcionController::class)
        ->whereIn('accion', ['registro', 'inicio'])
        ->middleware('guest')
        ->name('mascotas.solicitar-acceso');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:perfil')
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:eliminar-cuenta')
        ->name('profile.destroy');
});

// 'verified': solo usuarios con el correo verificado.
Route::middleware(['auth', 'verified', 'role:ciudadano'])->group(function () {
    Route::get('/mascotas/{mascota}/solicitudes-adopcion/create', [SolicitudAdopcionController::class, 'create'])
        ->name('solicitudes-adopcion.create');

    Route::post('/mascotas/{mascota}/solicitudes-adopcion', [SolicitudAdopcionController::class, 'store'])
        ->middleware('throttle:formularios')
        ->name('solicitudes-adopcion.store');

    Route::get('/postulaciones-voluntariado/create', [PostulacionVoluntariadoController::class, 'create'])->name('postulaciones-voluntariado.create');
    Route::post('/postulaciones-voluntariado', [PostulacionVoluntariadoController::class, 'store'])->middleware('throttle:formularios')->name('postulaciones-voluntariado.store');
});

// Consultar las propias solicitudes y postulaciones: ciudadano y voluntario (al aprobarse la
// postulación el rol pasa a voluntario y conserva su historial). Crear (create/store) sigue siendo
// solo para ciudadano. Cada show() verifica que el registro sea del usuario.
Route::middleware(['auth', 'verified', 'role:ciudadano,voluntario'])->group(function () {
    // «Mi espacio»: inicio del ciudadano y del voluntario al iniciar sesión.
    Route::get('/mi-espacio', MiEspacioController::class)->name('inicio');

    Route::get('/mis-solicitudes-adopcion', [SolicitudAdopcionController::class, 'index'])
        ->name('solicitudes-adopcion.index');

    Route::get('/mis-solicitudes-adopcion/{solicitud}', [SolicitudAdopcionController::class, 'show'])
        ->name('solicitudes-adopcion.show');

    Route::patch('/mis-solicitudes-adopcion/{solicitud}/cancelar', [SolicitudAdopcionController::class, 'cancel'])
        ->name('solicitudes-adopcion.cancel');

    Route::get('/mis-postulaciones-voluntariado', [PostulacionVoluntariadoController::class, 'index'])->name('postulaciones-voluntariado.index');
    Route::get('/mis-postulaciones-voluntariado/{postulacion}', [PostulacionVoluntariadoController::class, 'show'])->name('postulaciones-voluntariado.show');
});

Route::middleware(['auth', 'verified', 'role:voluntario'])->group(function () {
    Route::get('/seguimientos', [SeguimientoAdopcionController::class, 'index'])->name('seguimientos.index');
    Route::get('/seguimientos/{seguimiento}', [SeguimientoAdopcionController::class, 'show'])->name('seguimientos.show');
    Route::get('/seguimientos/{seguimiento}/visitas/{visita}/foto', [SeguimientoAdopcionController::class, 'visitPhoto'])->name('seguimientos.visitas.foto');
    Route::post('/seguimientos/{seguimiento}/visitas', [SeguimientoAdopcionController::class, 'storeVisit'])->middleware('throttle:formularios')->name('seguimientos.visitas.store');
    Route::patch('/seguimientos/{seguimiento}/visitas/{visita}/realizar', [SeguimientoAdopcionController::class, 'completeVisit'])->name('seguimientos.visitas.complete');
    Route::patch('/seguimientos/{seguimiento}/visitas/{visita}/reprogramar', [SeguimientoAdopcionController::class, 'rescheduleVisit'])->name('seguimientos.visitas.reschedule');
    Route::patch('/seguimientos/{seguimiento}/visitas/{visita}/no-asistio', [SeguimientoAdopcionController::class, 'markNoShow'])->name('seguimientos.visitas.no-show');
    Route::post('/seguimientos/{seguimiento}/incidencias', [SeguimientoAdopcionController::class, 'storeIncident'])->middleware('throttle:formularios')->name('seguimientos.incidencias.store');
    Route::patch('/seguimientos/{seguimiento}/incidencias/{incidencia}/atender', [SeguimientoAdopcionController::class, 'attendIncident'])->name('seguimientos.incidencias.attend');
});

Route::middleware(['auth', 'verified', 'role:administrador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/panel', [PanelPrincipalController::class, 'index'])
            ->name('dashboard');

        Route::get('/usuarios', [UserController::class, 'index'])
            ->name('usuarios.index');

        Route::patch('/usuarios/{user}/estado', [UserController::class, 'updateStatus'])
            ->name('usuarios.estado.update');

        Route::get('/usuarios/{user}', [UserController::class, 'show'])
            ->name('usuarios.show');

        Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])
            ->name('usuarios.edit');

        Route::patch('/usuarios/{user}', [UserController::class, 'update'])
            ->name('usuarios.update');

        Route::get('/mascotas', [MascotaController::class, 'index'])
            ->name('mascotas.index');

        Route::get('/mascotas/create', [MascotaController::class, 'create'])
            ->name('mascotas.create');

        Route::post('/mascotas', [MascotaController::class, 'store'])
            ->name('mascotas.store');

        Route::get('/mascotas/{mascota}/editar', [MascotaController::class, 'edit'])
            ->name('mascotas.edit');

        Route::patch('/mascotas/{mascota}', [MascotaController::class, 'update'])
            ->name('mascotas.update');

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

        Route::get('/solicitudes-adopcion/{solicitud}/compromiso', [AdminSolicitudAdopcionController::class, 'commitmentPdf'])
            ->name('solicitudes-adopcion.commitment-pdf');

        Route::post('/solicitudes-adopcion/{solicitud}/compromiso-evidencia', [AdminSolicitudAdopcionController::class, 'uploadCommitmentEvidence'])
            ->middleware('throttle:formularios')
            ->name('solicitudes-adopcion.commitment-evidence.store');

        Route::get('/solicitudes-adopcion/{solicitud}/compromiso-evidencia', [AdminSolicitudAdopcionController::class, 'downloadCommitmentEvidence'])
            ->name('solicitudes-adopcion.commitment-evidence.download');

        Route::patch('/solicitudes-adopcion/{solicitud}/evaluar', [AdminSolicitudAdopcionController::class, 'evaluate'])
            ->name('solicitudes-adopcion.evaluate');

        Route::patch('/solicitudes-adopcion/{solicitud}/aprobar', [AdminSolicitudAdopcionController::class, 'approve'])
            ->name('solicitudes-adopcion.approve');

        Route::patch('/solicitudes-adopcion/{solicitud}/registrar-entrega', [AdminSolicitudAdopcionController::class, 'deliver'])
            ->name('solicitudes-adopcion.deliver');

        Route::patch('/solicitudes-adopcion/{solicitud}/entrega-no-concretada', [AdminSolicitudAdopcionController::class, 'markNotDelivered'])
            ->name('solicitudes-adopcion.not-delivered');

        Route::patch('/solicitudes-adopcion/{solicitud}/rechazar', [AdminSolicitudAdopcionController::class, 'reject'])
            ->name('solicitudes-adopcion.reject');

        Route::get('/seguimientos', [AdminSeguimientoAdopcionController::class, 'index'])->name('seguimientos.index');
        Route::get('/seguimientos/{seguimiento}', [AdminSeguimientoAdopcionController::class, 'show'])->name('seguimientos.show');
        Route::get('/seguimientos/{seguimiento}/visitas/{visita}/foto', [AdminSeguimientoAdopcionController::class, 'visitPhoto'])->name('seguimientos.visitas.foto');
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
        Route::get('/reportes/pdf', [ReporteController::class, 'pdf'])->name('reportes.pdf');
        Route::get('/reportes/excel', [ReporteController::class, 'excel'])->name('reportes.excel');

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    });

require __DIR__.'/auth.php';
