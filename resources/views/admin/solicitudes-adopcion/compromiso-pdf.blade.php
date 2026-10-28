@php
    // Acta para imprimir, firmar y sellar el día de la entrega (dompdf). Es válida solo con firmas y sello originales;
    // en el sistema la firma se confirma aparte, al registrar la entrega.
    $aprobadaPor ??= null;
    $entregadaPor ??= null;
    $mascota = $solicitud->mascota;
    $adoptante = $solicitud->solicitante;
    $logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/pol-bolivia/logos/POL_02_horizontal_web.png')));
    $nombre = fn ($usuario) => $usuario ? trim($usuario->nombres.' '.$usuario->apellidos) : null;
    $fecha = fn ($instante) => $instante?->timezone('America/La_Paz')->translatedFormat('j \d\e F \d\e Y');
    $sexo = ['macho' => 'Macho', 'hembra' => 'Hembra'][$mascota->sexo] ?? '—';
    $tamano = ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'][$mascota->tamano] ?? '—';
    $especie = ['perro' => 'Perro', 'gato' => 'Gato'][$mascota->especie] ?? ucfirst((string) $mascota->especie);
    $edad = $mascota->edad !== null ? $mascota->edad_formateada.' aprox.' : '—';
    $visita1 = (int) config('pol.seguimiento.visitas.1.dias_sugeridos', 15);
    $visita2 = (int) config('pol.seguimiento.visitas.2.dias_sugeridos', 90);
    // Firma por POL-Bolivia: quien registró la entrega (cerró el caso); antes de la entrega, quien aprobó.
    $responsable = $nombre($entregadaPor) ?? $nombre($aprobadaPor);
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acta de adopción {{ $solicitud->numero_acta }}</title>
<style>
    @page { margin: 1.2cm 1.8cm 1.3cm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #222; font-size: 8.8pt; line-height: 1.32; }
    table { border-collapse: collapse; width: 100%; }

    .membrete td { vertical-align: bottom; }
    .membrete .logo { width: 140px; }
    .membrete .der { text-align: right; font-size: 8pt; color: #555; line-height: 1.5; }
    .membrete .numero { font-size: 11pt; font-weight: bold; color: #1d3f66; }
    .regla { border-bottom: 1.5px solid #1d3f66; margin: 6px 0 10px; }

    h1 { font-size: 15pt; font-weight: bold; color: #1d3f66; margin: 0 0 4px; }
    .intro { margin: 0 0 6px; color: #333; }

    h2 { font-size: 8.5pt; font-weight: bold; color: #1d3f66; text-transform: uppercase; letter-spacing: .6px; margin: 10px 0 3px; padding-bottom: 2px; border-bottom: .5px solid #b8c4d2; }

    .datos td { padding: 1.6px 0; vertical-align: top; }
    .datos .et { width: 16%; color: #666; }
    .datos .val { width: 34%; padding-right: 14px; }
    .llenar { display: inline-block; border-bottom: .6px solid #444; width: 150px; }
    .llenar.corta { width: 40px; }

    ol { margin: 0; padding-left: 16px; }
    ol li { margin-bottom: 2px; padding-left: 3px; }
    .nota { margin: 6px 0 0; }

    .firmas { margin-top: 40px; page-break-inside: avoid; }
    .firmas td { vertical-align: bottom; text-align: center; font-size: 8pt; color: #444; }
    .firmas .linea { border-top: .7px solid #222; padding-top: 4px; }
    .firmas .quien { font-size: 8.5pt; color: #222; font-weight: bold; }
    .sello { border: .7px dashed #999; height: 70px; width: 100px; margin: 0 auto 4px; }

    .pie { position: fixed; bottom: -0.75cm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #888; }
</style>
</head>
<body>
    <div class="pie">POL-Bolivia · Acta {{ $solicitud->numero_acta }} · Válida solo con firmas y sello originales</div>

    <table class="membrete">
        <tr>
            <td><img class="logo" src="{{ $logo }}" alt="POL-Bolivia"></td>
            <td class="der"><span class="numero">Acta N.º {{ $solicitud->numero_acta ?? 'sin asignar' }}</span><br>Cochabamba, Bolivia</td>
        </tr>
    </table>
    <div class="regla"></div>

    <h1>Acta de adopción</h1>
    <p class="intro">POL-Bolivia entrega en adopción a la mascota descrita en esta acta. Con su firma, la persona adoptante acepta los compromisos detallados a continuación.</p>

    <h2>Persona adoptante</h2>
    <table class="datos">
        <tr>
            <td class="et">Nombre</td><td class="val">{{ $adoptante->nombres }} {{ $adoptante->apellidos }}</td>
            <td class="et">C.I.</td><td class="val"><span class="llenar">&nbsp;</span></td>
        </tr>
        <tr>
            <td class="et">Teléfono</td><td class="val">{{ $adoptante->telefono ?: '—' }}</td>
            <td class="et">Zona</td><td class="val">{{ $solicitud->zona_aproximada }}</td>
        </tr>
        <tr>
            <td class="et">Dirección</td><td class="val" colspan="3"><span class="llenar" style="width: 360px;">&nbsp;</span></td>
        </tr>
    </table>

    <h2>Mascota</h2>
    <table class="datos">
        <tr>
            <td class="et">Nombre</td><td class="val">{{ $mascota->nombre }}</td>
            <td class="et">Especie</td><td class="val">{{ $especie }}</td>
        </tr>
        <tr>
            <td class="et">Sexo</td><td class="val">{{ $sexo }}</td>
            <td class="et">Edad</td><td class="val">{{ $edad }}</td>
        </tr>
        <tr>
            <td class="et">Raza</td><td class="val">{{ $mascota->raza ?: 'Mestiza' }} · {{ $tamano }}</td>
            <td class="et">Color</td><td class="val">{{ $mascota->color ?: '—' }}</td>
        </tr>
    </table>

    <h2>Adopción</h2>
    <table class="datos">
        <tr>
            <td class="et">Aprobada</td>
            <td class="val">{{ $fecha($solicitud->aprobada_at) ?? '—' }}</td>
            <td class="et">Aprobada por</td>
            <td class="val">{{ $nombre($aprobadaPor) ?? '—' }}</td>
        </tr>
        <tr>
            <td class="et">Entrega</td>
            <td class="val">@if ($solicitud->entregada_at){{ $fecha($solicitud->entregada_at) }}@else<span class="llenar">&nbsp;</span>@endif</td>
            <td class="et">Entregada por</td>
            <td class="val">@if ($entregadaPor){{ $nombre($entregadaPor) }}@else<span class="llenar">&nbsp;</span>@endif</td>
        </tr>
    </table>

    <h2>Compromisos de la persona adoptante</h2>
    <ol>
        <li>Darle alimentación adecuada, agua limpia, un lugar seguro y protegido, y atención veterinaria cuando la necesite.</li>
        <li>Mantener al día sus vacunas y desparasitaciones y, si aún no está esterilizada, esterilizarla cuando el veterinario lo indique.</li>
        <li>No maltratarla, abandonarla ni mantenerla encerrada o encadenada de forma permanente (Ley N.º 700 de defensa de los animales).</li>
        <li>No venderla, regalarla ni entregarla a otra persona.</li>
        <li>Si ya no puede cuidarla, avisar y devolverla a POL-Bolivia. Nunca abandonarla.</li>
        <li>Permitir las visitas de seguimiento de POL-Bolivia, aproximadamente a los {{ $visita1 }} y a los {{ $visita2 }} días de la entrega.</li>
        <li>Avisar a POL-Bolivia si cambia de domicilio o de teléfono, o si la mascota se pierde, enferma gravemente o fallece.</li>
    </ol>
    <p class="nota">Si se comprueba maltrato, abandono o el incumplimiento de estos compromisos, POL-Bolivia podrá pedir la devolución de la mascota para proteger su bienestar. La persona adoptante declara que los datos de su solicitud son verdaderos.</p>

    <table class="firmas">
        <tr>
            <td style="width: 37%;"><div class="linea"><span class="quien">{{ $adoptante->nombres }} {{ $adoptante->apellidos }}</span><br>Persona adoptante · C.I. <span class="llenar corta" style="width: 70px;">&nbsp;</span></div></td>
            <td style="width: 5%;"></td>
            <td style="width: 37%;"><div class="linea">@if ($responsable)<span class="quien">{{ $responsable }}</span>@else<span class="llenar">&nbsp;</span>@endif<br>Por POL-Bolivia</div></td>
            <td style="width: 5%;"></td>
            <td style="width: 16%;"><div class="sello"></div>Sello</td>
        </tr>
    </table>
</body>
</html>
