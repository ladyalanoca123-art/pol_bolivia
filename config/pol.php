<?php

/*
 * Parámetros propios de POL-Bolivia.
 */
return [

    // Panel principal del administrador (App\Services\PanelAdministrador).
    'panel' => [
        // Días sin respuesta a partir de los cuales una solicitud pendiente genera aviso.
        'solicitud_dias_alerta' => 7,
        // Días sin voluntario a partir de los cuales un seguimiento genera aviso.
        'seguimiento_sin_asignar_dias' => 3,
        // Días sin revisión a partir de los cuales una postulación genera aviso.
        'postulacion_dias_alerta' => 7,
        // Días que abarca la agenda de visitas, contando desde hoy.
        'agenda_dias' => 7,
        // Registros por lista en la bandeja «Requiere tu atención» y en la agenda.
        'limite_lista' => 5,
        // Entradas de la actividad reciente.
        'actividad_limite' => 8,
    ],

    // Fotos de mascotas (App\Rules\ImagenLegible::reglas()).
    'mascotas' => [
        // Peso máximo de cada foto, en kilobytes. PHP debe admitirlo: upload_max_filesize y post_max_size.
        'foto_max_kb' => 10240,
    ],

    // Administrador inicial (Database\Seeders\AdministradorInicialSeeder). Solo se usa al crearlo por primera vez.
    // Se lee desde aquí y no con env() para que funcione con "php artisan config:cache".
    'administrador_inicial' => [
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],

];
