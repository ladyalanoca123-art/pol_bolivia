@extends('layouts.public')

@section('title', 'Política de privacidad | POL-Bolivia')

@section('content')
    @php($correo = config('pol.contacto.correo'))
    <x-documento-legal titulo="Política de privacidad" resumen="Qué datos tuyos guardamos, para qué los usamos, quién los ve y cómo puedes pedir que se corrijan o se borren.">
        <h2>1. Quiénes somos</h2>
        <p>
            <strong>POL-Bolivia</strong> («POL») es un colectivo de voluntarios y activistas por el bienestar animal de Cochabamba, Bolivia.
            Somos responsables de los datos que se registran en este sistema. Contacto: <a href="mailto:{{ $correo }}">{{ $correo }}</a>.
        </p>

        <h2>2. Qué datos pedimos</h2>
        <p>Pedimos solo lo necesario para cada trámite:</p>
        <ul>
            <li><strong>Al crear tu cuenta:</strong> nombres, apellidos, correo electrónico y contraseña. El teléfono es opcional para registrarte. La contraseña se guarda cifrada: nadie, ni siquiera POL, puede verla.</li>
            <li><strong>Al solicitar una adopción:</strong> tu teléfono (WhatsApp), las respuestas del cuestionario sobre tu vivienda, tu hogar, tu experiencia con mascotas y tu rutina, la <strong>zona aproximada</strong> donde vives (no tu dirección exacta) y tus horarios para las visitas.</li>
            <li><strong>Al concretar una adopción:</strong> la fotografía del acta de adopción firmada.</li>
            <li><strong>Durante el seguimiento:</strong> las fechas y observaciones de las visitas, una foto de la visita si se toma y las incidencias que se reporten sobre el bienestar de la mascota.</li>
            <li><strong>Si te postulas al voluntariado:</strong> tu experiencia, áreas de interés, habilidades, disponibilidad, motivación y zona preferida.</li>
            <li><strong>Datos técnicos:</strong> la dirección IP y el navegador desde los que aceptas estos documentos o realizas cambios importantes, para dejar constancia y proteger tu cuenta.</li>
        </ul>
        <p>No pedimos tu carnet de identidad, tu dirección exacta ni datos bancarios.</p>

        <h2>3. Para qué los usamos</h2>
        <ul>
            <li>Gestionar tu cuenta y tu acceso al sistema.</li>
            <li>Evaluar tu solicitud y contactarte por WhatsApp o llamada para coordinar la adopción y la entrega.</li>
            <li>Hacer el seguimiento posterior a la adopción, por el bienestar de la mascota.</li>
            <li>Revisar tu postulación al voluntariado.</li>
            <li>Mantener la seguridad del sistema y un registro de quién hizo cada cambio.</li>
        </ul>
        <p><strong>No usamos tus datos para publicidad, no los vendemos y no los compartimos con terceros</strong>, salvo que una autoridad competente lo exija conforme a la ley.</p>

        <h2>4. Quién puede verlos</h2>
        <ul>
            <li><strong>Los administradores de POL</strong>, que gestionan las adopciones y el voluntariado.</li>
            <li><strong>El voluntario asignado a tu seguimiento</strong> ve tu nombre, tu teléfono y tu zona aproximada, únicamente para coordinar las visitas.</li>
            <li>El público solo ve la información de las mascotas, nunca datos de las personas.</li>
        </ul>
        <p>Si nos escribes o te escribimos por WhatsApp, esa conversación también se rige por las condiciones de WhatsApp.</p>

        <h2>5. Cuánto tiempo los guardamos</h2>
        <ul>
            <li>Los datos de tu cuenta, mientras la cuenta exista.</li>
            <li>Las solicitudes, actas y seguimientos de una adopción se conservan como historial del caso, por el bienestar del animal y para saber quién se encargó de cada decisión.</li>
            <li>Los registros de seguridad se borran automáticamente a los {{ config('logging.channels.seguridad.days', 90) }} días.</li>
            <li>Las copias de seguridad de la base de datos se renuevan periódicamente.</li>
        </ul>

        <h2>6. Tus derechos</h2>
        <p>Conforme a los artículos 21 y 130 de la Constitución Política del Estado, puedes:</p>
        <ul>
            <li><strong>Ver tus datos:</strong> en «Mi perfil» y «Mis solicitudes».</li>
            <li><strong>Corregirlos:</strong> desde «Mi perfil», o escribiéndonos.</li>
            <li><strong>Pedir que se borren:</strong> si no tienes solicitudes ni adopciones registradas, puedes eliminar tu cuenta desde «Mi perfil». Si tienes historial de adopción, se conserva por el bienestar de la mascota; escríbenos y te explicamos qué podemos hacer en tu caso.</li>
            <li><strong>Oponerte</strong> a un uso de tus datos o retirar tu consentimiento, escribiéndonos.</li>
        </ul>
        <p>Para cualquiera de estos pedidos, escribe a <a href="mailto:{{ $correo }}">{{ $correo }}</a>.</p>

        <h2>7. Cómo los protegemos</h2>
        <ul>
            <li>Contraseñas cifradas y límites de intentos para evitar accesos indebidos.</li>
            <li>Cada persona ve solo lo que le corresponde según su rol.</li>
            <li>Las actas y las fotos de las visitas son privadas: no se pueden ver desde internet sin permiso.</li>
            <li>Las fotos subidas se procesan de nuevo y se les quitan los datos ocultos, como la ubicación GPS.</li>
            <li>Conexión cifrada (HTTPS) cuando el sistema está publicado en internet, registro de cambios y copias de seguridad.</li>
        </ul>

        <h2>8. Edad mínima</h2>
        <p>El sistema es para personas de {{ config('pol.legal.edad_minima') }} años o más. No registramos a sabiendas datos de menores de edad.</p>

        <h2>9. Cookies</h2>
        <p>Usamos solo las cookies necesarias para que el sistema funcione. Lo explicamos en la <a href="{{ route('legal.cookies') }}">Política de cookies</a>.</p>

        <h2>10. Cambios en esta política</h2>
        <p>Si esta política cambia, actualizaremos la fecha de vigencia y te pediremos que la aceptes de nuevo la próxima vez que inicies sesión.</p>

        <h2>11. Normativa aplicable</h2>
        <p>Esta política se rige por las leyes del Estado Plurinacional de Bolivia, en particular la Constitución Política del Estado (artículos 21 y 130), la Ley N.º 164 General de Telecomunicaciones, Tecnologías de Información y Comunicación y su Reglamento (Decreto Supremo N.º 1793).</p>
    </x-documento-legal>
@endsection
