@extends('layouts.public')

@section('title', 'Términos y condiciones | POL-Bolivia')

@section('content')
    @php
        $edad = config('pol.legal.edad_minima');
        $maxActivas = \App\Models\SolicitudAdopcion::MAX_ACTIVAS;
        $visita1 = config('pol.seguimiento.visitas.1.dias_sugeridos');
        $visita2 = config('pol.seguimiento.visitas.2.dias_sugeridos');
    @endphp
    <x-documento-legal titulo="Términos y condiciones" resumen="Las reglas para usar el sistema de POL-Bolivia: crear una cuenta, solicitar una adopción, acompañar el seguimiento y postularte al voluntariado.">
        <h2>1. Quiénes somos</h2>
        <p><strong>POL-Bolivia</strong> («POL») es un colectivo de voluntarios y activistas por el bienestar animal de Cochabamba, Bolivia. Este sistema sirve para dar en adopción a las mascotas que cuidamos y para organizar a nuestros voluntarios.</p>

        <h2>2. El servicio es gratuito</h2>
        <p>Usar el sistema y adoptar una mascota de POL <strong>no tiene ningún costo</strong>. El sistema no realiza cobros ni pagos; por eso no existe una política de reembolsos.</p>

        <h2>3. Tu cuenta</h2>
        <ul>
            <li>Para crear una cuenta debes tener <strong>{{ $edad }} años o más</strong>.</li>
            <li>La información que registres debe ser verdadera y estar actualizada.</li>
            <li>Tu cuenta es personal: cuida tu contraseña y no la compartas. Eres responsable de lo que se haga con ella.</li>
            <li>POL puede desactivar una cuenta que incumpla estos términos o que se use para dañar a las mascotas o a otras personas.</li>
        </ul>

        <h2>4. Cómo funciona la adopción</h2>
        <ul>
            <li>Enviar una solicitud <strong>no garantiza la adopción</strong>. POL revisa cada solicitud y se comunica contigo por WhatsApp o llamada para conocerte.</li>
            <li>Puedes tener hasta {{ $maxActivas }} solicitudes en trámite a la vez.</li>
            <li>Si la solicitud se aprueba, la mascota queda <strong>reservada</strong> para ti y coordinamos la entrega.</li>
            <li>En la entrega se firma un <strong>acta de adopción</strong>, que detalla los compromisos de la persona que adopta.</li>
            <li>Después de la adopción, POL realiza <strong>2 visitas de seguimiento</strong> (aproximadamente a los {{ $visita1 }} y a los {{ $visita2 }} días), coordinadas contigo.</li>
            <li>La información sobre la salud y el carácter de cada mascota se da de buena fe, según lo que conocemos; puede cambiar con el tiempo.</li>
        </ul>

        <h2>5. Compromisos de quien adopta</h2>
        <ul>
            <li>Dar a la mascota alimento, refugio, atención veterinaria, vacunas y desparasitación al día.</li>
            <li>No venderla, regalarla ni cederla a otras personas sin comunicarlo antes a POL.</li>
            <li>Permitir el seguimiento acordado.</li>
            <li>Cumplir lo firmado en el acta de adopción y la normativa de tenencia responsable, como la Ley Municipal N.º 1147/2022 de Tenencia, Protección y Bienestar Animal de Cochabamba.</li>
        </ul>
        <p>Si se detecta maltrato, abandono o un riesgo para la mascota, POL actuará según lo acordado en el acta de adopción y podrá comunicarlo a las autoridades competentes.</p>

        <h2>6. Voluntariado</h2>
        <ul>
            <li>Cualquier persona con cuenta puede postularse. POL revisa cada postulación y decide su aprobación.</li>
            <li>Los voluntarios usan los datos de las familias <strong>solo para coordinar las visitas de seguimiento</strong> y no pueden compartirlos ni usarlos para otros fines.</li>
        </ul>

        <h2>7. Uso correcto del sistema</h2>
        <p>No está permitido:</p>
        <ul>
            <li>Registrar datos falsos o hacerse pasar por otra persona.</li>
            <li>Usar el sistema para vender animales o con fines comerciales.</li>
            <li>Intentar entrar a cuentas o información ajenas, o afectar el funcionamiento del sistema.</li>
        </ul>

        <h2>8. Imágenes y contenido</h2>
        <p>Las fotografías y la información de las mascotas pertenecen a POL-Bolivia. Algunas ilustraciones del sitio fueron generadas con inteligencia artificial. No se permite usar este contenido con fines comerciales.</p>

        <h2>9. Tus datos</h2>
        <p>Cómo tratamos tu información se explica en la <a href="{{ route('legal.privacidad') }}">Política de privacidad</a>, y las cookies en la <a href="{{ route('legal.cookies') }}">Política de cookies</a>.</p>

        <h2>10. Disponibilidad</h2>
        <p>Hacemos lo posible para que el sistema funcione siempre, pero puede haber interrupciones por mantenimiento o fallas técnicas.</p>

        <h2>11. Cambios en estos términos</h2>
        <p>Si estos términos cambian, actualizaremos la fecha de vigencia y te pediremos que los aceptes de nuevo la próxima vez que inicies sesión.</p>

        <h2>12. Ley aplicable</h2>
        <p>Estos términos se rigen por las leyes del Estado Plurinacional de Bolivia. Cualquier diferencia se intentará resolver primero mediante el diálogo con POL.</p>
    </x-documento-legal>
@endsection
