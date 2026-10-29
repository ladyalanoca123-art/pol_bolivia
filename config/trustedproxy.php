<?php

/*
 * Intermediarios de confianza (Illuminate\Http\Middleware\TrustProxies).
 *
 * En la nube el sistema suele estar detrás de un intermediario (balanceador, Cloudflare, Nginx) que
 * recibe la conexión y la reenvía. Sin esta configuración Laravel vería la IP del intermediario
 * para todos los visitantes: el límite de intentos de inicio de sesión bloquearía a todos a la vez
 * y no detectaría que la conexión original es HTTPS.
 *
 * TRUSTED_PROXIES:
 * - sin definir: no se confía en ningún intermediario (desarrollo local o servidor sin intermediario);
 * - «*»: se confía en el intermediario que se conecta (solo si el servidor no es accesible directamente);
 * - lista de IP o rangos separados por comas (lo más seguro), p. ej. 10.0.0.0/8,172.16.0.0/12.
 */
return [

    'proxies' => env('TRUSTED_PROXIES'),

];
