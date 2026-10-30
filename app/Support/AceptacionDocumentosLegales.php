<?php

namespace App\Support;

use App\Models\AceptacionLegal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Términos y condiciones y Política de privacidad: versión vigente (config pol.legal.version) y registro de
 * quién las aceptó, cuándo, desde qué IP y con qué navegador (tabla aceptaciones_legales).
 */
class AceptacionDocumentosLegales
{
    public const DOCUMENTOS = ['terminos', 'privacidad'];

    public static function versionVigente(): string
    {
        return (string) config('pol.legal.version');
    }

    /** Si la persona todavía no aceptó alguno de los documentos en su versión vigente. */
    public static function pendiente(User $usuario): bool
    {
        $aceptados = AceptacionLegal::query()
            ->where('usuario_id', $usuario->id)
            ->where('version', self::versionVigente())
            ->whereIn('documento', self::DOCUMENTOS)
            ->distinct()
            ->count('documento');

        return $aceptados < count(self::DOCUMENTOS);
    }

    /** Registra la aceptación de los dos documentos en su versión vigente (si ya estaba, no la duplica). */
    public static function registrar(User $usuario, ?Request $peticion, string $origen): void
    {
        foreach (self::DOCUMENTOS as $documento) {
            AceptacionLegal::query()->firstOrCreate(
                ['usuario_id' => $usuario->id, 'documento' => $documento, 'version' => self::versionVigente()],
                [
                    'origen' => $origen,
                    'ip' => $peticion?->ip(),
                    'user_agent' => $peticion ? Str::limit((string) $peticion->userAgent(), 250, '') : null,
                    'aceptado_at' => now(),
                ],
            );
        }
    }
}
