<?php

/*
 * Parámetros propios de POL-Bolivia.
 */
return [

    // Panel principal del administrador (App\Services\PanelAdministrador).
    'panel' => [
        // Días sin respuesta a partir de los cuales una solicitud pendiente genera aviso.
        'solicitud_dias_alerta' => 7,
        // Días desde la aprobación sin registrar la entrega a partir de los cuales una solicitud aprobada genera aviso.
        'aprobada_dias_alerta' => 7,
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

    // Botones «Escribir por WhatsApp» y «Llamar» (App\Support\ContactoTelefonico): solo para la administración y
    // para el voluntario asignado al seguimiento. :nombre, :mascota y :voluntario se reemplazan al armar el enlace.
    'whatsapp' => [
        // Prefijo internacional de Bolivia, sin «+».
        'prefijo' => '591',
        'mensajes' => [
            'en_espera' => 'Hola :nombre, te escribimos de POL-Bolivia por tu solicitud para adoptar a :mascota. ¿Cuándo podemos conversar unos minutos?',
            'aprobada' => 'Hola :nombre, ¡tu solicitud para adoptar a :mascota fue aprobada! Coordinemos el día y lugar de la entrega.',
            'seguimiento' => 'Hola :nombre, soy :voluntario, voluntario/a de POL-Bolivia. Quisiera coordinar la visita de seguimiento de :mascota.',
        ],
    ],

    // Fechas sugeridas para el seguimiento, contadas desde la fecha real de entrega.
    'seguimiento' => [
        'visitas' => [
            1 => ['dias_sugeridos' => 15, 'margen_minimo' => 10, 'margen_maximo' => 20],
            2 => ['dias_sugeridos' => 90, 'margen_minimo' => 75, 'margen_maximo' => 105],
        ],
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
