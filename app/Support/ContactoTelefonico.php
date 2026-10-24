<?php

namespace App\Support;

use App\Rules\TelefonoBoliviano;

/**
 * Enlaces «Escribir por WhatsApp» (https://wa.me/591XXXXXXXX?text=...) y «Llamar» (tel:+591XXXXXXXX) a partir del
 * teléfono guardado de una persona. Solo números móviles bolivianos válidos (TelefonoBoliviano::PATRON); con un
 * teléfono vacío o inválido no hay enlaces y la vista muestra un aviso.
 */
class ContactoTelefonico
{
    public const SIN_TELEFONO = 'El solicitante no tiene un teléfono válido registrado.';

    /** Número internacional sin «+» (p. ej. 59171234567), o null si el teléfono no es válido. */
    public static function numeroInternacional(?string $telefono): ?string
    {
        $prefijo = (string) config('pol.whatsapp.prefijo', '591');
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        // Guardado con el prefijo internacional: se conservan los 8 dígitos nacionales.
        if (strlen($digitos) === strlen($prefijo) + 8 && str_starts_with($digitos, $prefijo)) {
            $digitos = substr($digitos, strlen($prefijo));
        }

        return preg_match(TelefonoBoliviano::PATRON, $digitos) ? $prefijo.$digitos : null;
    }

    /** Enlace de WhatsApp con el mensaje de config('pol.whatsapp.mensajes.<clave>'), o null sin teléfono válido. */
    public static function whatsapp(?string $telefono, string $clave, array $datos): ?string
    {
        $numero = self::numeroInternacional($telefono);

        if ($numero === null) {
            return null;
        }

        return 'https://wa.me/'.$numero.'?text='.rawurlencode(self::mensaje($clave, $datos));
    }

    public static function llamada(?string $telefono): ?string
    {
        $numero = self::numeroInternacional($telefono);

        return $numero === null ? null : 'tel:+'.$numero;
    }

    /** Texto del mensaje con :nombre, :mascota y :voluntario reemplazados. */
    public static function mensaje(string $clave, array $datos): string
    {
        $plantilla = (string) config("pol.whatsapp.mensajes.{$clave}");
        $reemplazos = [];

        foreach ($datos as $campo => $valor) {
            $reemplazos[':'.$campo] = trim((string) $valor);
        }

        return strtr($plantilla, $reemplazos);
    }

    /** Primer nombre, para un saludo cercano («Hola Ana»). */
    public static function primerNombre(?string $nombres): string
    {
        return strtok(trim((string) $nombres), ' ') ?: '';
    }
}
