{{--
    Plantilla de las páginas de error de POL-Bolivia (413, 419, 429 y 500).
    No usa @vite, base de datos ni sesión: debe mostrarse aunque falle cualquier otra parte del sistema.
    Nunca muestra trazas, consultas SQL ni rutas internas.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') | POL-Bolivia</title>
    @include('partials.favicon')
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
               background: #f8fafc; color: #1e293b; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .tarjeta { width: 100%; max-width: 32rem; background: #fff; border-radius: 1rem; padding: 2.5rem 2rem; text-align: center;
                   box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
        .logo { height: 4rem; width: auto; max-width: 100%; }
        .codigo { margin: 2rem 0 0; font-size: .875rem; font-weight: 700; letter-spacing: .14em; color: #b45309; }
        h1 { margin: .5rem 0 0; font-size: 1.75rem; line-height: 1.2; font-weight: 800; color: #172554; }
        .mensaje { margin: 1rem 0 0; color: #475569; line-height: 1.6; }
        .linea { margin: 1.5rem auto 0; height: 4px; width: 4rem; border-radius: 9999px; background: #fbbf24; }
        .boton { display: inline-flex; align-items: center; justify-content: center; margin-top: 2rem; height: 3rem; padding: 0 2rem;
                 border-radius: 9999px; background: #fbbf24; color: #172554; font-weight: 800; text-decoration: none; }
        .boton:hover { background: #fcd34d; }
        .boton:focus { outline: none; box-shadow: 0 0 0 4px rgba(29, 78, 216, .2); }
    </style>
</head>
<body>
    <main class="tarjeta">
        <img src="{{ asset('images/pol-bolivia/logos/POL_02_horizontal_web.png') }}" alt="POL-Bolivia, Hacedores de Sueños" class="logo">
        <p class="codigo">@yield('codigo')</p>
        <h1>@yield('titulo')</h1>
        <p class="mensaje">@yield('mensaje')</p>
        <div class="linea" aria-hidden="true"></div>
        @yield('accion')
    </main>
</body>
</html>
