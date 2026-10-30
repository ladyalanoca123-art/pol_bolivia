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

    // Enlaces de WhatsApp (App\Support\ContactoTelefonico). «Escribir por WhatsApp» a una persona: solo para la
    // administración y el voluntario asignado al seguimiento. :nombre, :mascota y :voluntario se reemplazan al armar el enlace.
    'whatsapp' => [
        // Prefijo internacional de Bolivia, sin «+».
        'prefijo' => '591',
        // Botón flotante de consultas en las páginas públicas (x-whatsapp-flotante): número de contacto de POL-Bolivia,
        // 8 dígitos. Se puede cambiar con POL_WHATSAPP_CONSULTAS en .env; vacío o inválido, el botón no aparece.
        'consultas' => env('POL_WHATSAPP_CONSULTAS', '64630845'),
        'mensajes' => [
            'en_espera' => 'Hola :nombre, te escribimos de POL-Bolivia por tu solicitud para adoptar a :mascota. ¿Cuándo podemos conversar unos minutos?',
            'aprobada' => 'Hola :nombre, ¡tu solicitud para adoptar a :mascota fue aprobada! Coordinemos el día y lugar de la entrega.',
            'consulta' => 'Hola POL-Bolivia, quisiera hacer una consulta.',
            'consulta_mascota' => 'Hola POL-Bolivia, quisiera consultar sobre :mascota.',
            'seguimiento' => 'Hola :nombre, soy :voluntario, voluntario/a de POL-Bolivia. Quisiera coordinar la visita de seguimiento de :mascota.',
        ],
    ],

    // Contacto de POL-Bolivia en el pie de las páginas públicas. El teléfono es el de config('pol.whatsapp.consultas').
    'contacto' => [
        'correo' => env('POL_CORREO_CONTACTO', 'contacto.polbolivia@gmail.com'),
    ],

    // Documentos legales (resources/views/legal). Al cambiar el texto de los Términos o de la Política de privacidad,
    // sube la versión (fecha): cada persona deberá aceptarlos de nuevo al iniciar sesión (App\Support\AceptacionDocumentosLegales).
    'legal' => [
        'version' => '2026-10-29',
        'edad_minima' => 18,
    ],

    // Redes oficiales de POL-Bolivia: pie de las páginas públicas y aviso de «ENVIAR SOLICITUD» (x-redes-sociales).
    'redes' => [
        'whatsapp' => 'https://wa.me/59175239523',
        'facebook' => 'https://www.facebook.com/POLpaginaoficial',
        'instagram' => 'https://www.instagram.com/polgrupo/',
        'tiktok' => 'https://vm.tiktok.com/ZS9ULCPRfQA85-FoBQC/',
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
