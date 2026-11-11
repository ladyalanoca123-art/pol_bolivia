<?php

namespace App\Providers;

use App\Support\RegistroSeguridad;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurarLimitesDePeticiones();
        $this->configurarReglasDeContrasena();
        $this->registrarEventosDeSeguridad();

        // En producción todos los enlaces generados usan HTTPS.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Límites de peticiones (protección contra abuso, bots y DoS).
     * Se aplican en routes/web.php y routes/auth.php con el middleware "throttle:<nombre>".
     */
    private function configurarLimitesDePeticiones(): void
    {
        // Límite general para todo el sitio: 120 peticiones por minuto por usuario o IP.
        RateLimiter::for('general', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Páginas públicas (portada y catálogo): 60 por minuto por IP.
        RateLimiter::for('publico', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Crear cuenta: 5 por minuto y 20 por hora por IP (evita registros masivos de bots).
        RateLimiter::for('registro', fn (Request $request) => [
            Limit::perMinute(5)->by('registro-min:'.$request->ip()),
            Limit::perHour(20)->by('registro-hora:'.$request->ip()),
        ]);

        // Inicio de sesión (POST /login): 20 por minuto por IP. Es independiente del límite de
        // 5 intentos fallidos por correo e IP que aplica LoginRequest, y no lo reemplaza.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by('login:'.$request->ip()));

        // Pedir el enlace (POST /forgot-password): 3 por correo cada 15 minutos y 10 por IP por hora (evita spam de
        // correos). El correo cuenta exista o no la cuenta, así el 429 no revela nada; queda en el log "seguridad".
        RateLimiter::for('recuperar-clave', function (Request $request) {
            $correo = mb_strtolower(trim((string) $request->input('email')));
            $rechazo = function (Request $request, array $headers) use ($correo) {
                app(RegistroSeguridad::class)->recuperacionSolicitada(null, $correo, 'limite');

                return response()->view('errors.429', [], 429, $headers);
            };

            return [
                Limit::perMinutes(15, 3)->by('recuperar-correo:'.hash('sha256', $correo))->response($rechazo),
                Limit::perHour(10)->by('recuperar-ip:'.$request->ip())->response($rechazo),
            ];
        });

        // Cambiar la contraseña con el enlace (POST /reset-password): 5 por minuto por IP y 5 por correo cada 15 minutos.
        RateLimiter::for('restablecer-clave', fn (Request $request) => [
            Limit::perMinute(5)->by('restablecer-ip:'.$request->ip()),
            Limit::perMinutes(15, 5)->by('restablecer-correo:'.hash('sha256', mb_strtolower(trim((string) $request->input('email'))))),
        ]);

        // Envío de formularios (solicitudes, postulaciones, visitas, incidencias): 10 por minuto.
        RateLimiter::for('formularios', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        // «Mi perfil»: guardar los datos (PATCH /profile) y cambiar la contraseña (PUT /password), 5 por minuto
        // por usuario cada uno, para que la contraseña actual que piden no sirva para adivinarla. Página 429.
        RateLimiter::for('perfil', fn (Request $request) => Limit::perMinute(5)
            ->by('perfil:'.($request->user()?->id ?: $request->ip())));
        RateLimiter::for('cambiar-clave', fn (Request $request) => Limit::perMinute(5)
            ->by('cambiar-clave:'.($request->user()?->id ?: $request->ip())));

        // Eliminar la propia cuenta (DELETE /profile): 5 intentos por minuto por usuario, para que la confirmación
        // con contraseña no sirva para adivinarla. El rechazo queda en el log "seguridad" y usa la página 429.
        RateLimiter::for('eliminar-cuenta', fn (Request $request) => Limit::perMinute(5)
            ->by('eliminar-cuenta:'.($request->user()?->id ?: $request->ip()))
            ->response(function (Request $request, array $headers) {
                app(RegistroSeguridad::class)->eliminacionCuenta($request->user(), 'limite_de_intentos');

                return response()->view('errors.429', [], 429, $headers);
            }));
    }

    /**
     * Auditoría de autenticación en el canal de log "seguridad" (ver App\Support\RegistroSeguridad).
     */
    private function registrarEventosDeSeguridad(): void
    {
        Event::listen(Login::class, [RegistroSeguridad::class, 'login']);
        Event::listen(Failed::class, [RegistroSeguridad::class, 'fallido']);
        Event::listen(Lockout::class, [RegistroSeguridad::class, 'bloqueo']);
        Event::listen(Logout::class, [RegistroSeguridad::class, 'logout']);
    }

    /**
     * Reglas de contraseña segura para registro, cambio y restablecimiento.
     * Se usan donde aparece Password::defaults().
     */
    private function configurarReglasDeContrasena(): void
    {
        Password::defaults(function () {
            $regla = Password::min(8)->letters()->mixedCase()->numbers();

            // En producción además se rechazan contraseñas filtradas en internet
            // (consulta anónima a haveibeenpwned.com).
            return $this->app->isProduction() ? $regla->uncompromised() : $regla;
        });
    }
}
