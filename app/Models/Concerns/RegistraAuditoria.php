<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra automáticamente en la tabla "auditorias" cada vez que un registro
 * del modelo se crea, se modifica o se elimina: quién lo hizo, desde qué IP,
 * cuándo y qué campos cambiaron (valor anterior y nuevo).
 */
trait RegistraAuditoria
{
    /**
     * Campos que nunca se guardan en la auditoría.
     */
    protected static array $camposOcultosAuditoria = [
        'password', 'remember_token', 'updated_at', 'created_at',
    ];

    public static function bootRegistraAuditoria(): void
    {
        static::created(fn (Model $modelo) => static::auditar($modelo, 'creado', null, $modelo->getAttributes()));

        static::updated(function (Model $modelo): void {
            $nuevos = $modelo->getChanges();
            $anteriores = array_intersect_key($modelo->getOriginal(), $nuevos);

            static::auditar($modelo, 'actualizado', $anteriores, $nuevos);
        });

        static::deleted(fn (Model $modelo) => static::auditar($modelo, 'eliminado', $modelo->getOriginal(), null));
    }

    private static function auditar(Model $modelo, string $accion, ?array $anteriores, ?array $nuevos): void
    {
        $ocultos = array_flip(static::$camposOcultosAuditoria);
        $anteriores = $anteriores === null ? null : array_diff_key($anteriores, $ocultos);
        $nuevos = $nuevos === null ? null : array_diff_key($nuevos, $ocultos);

        // Una actualización que solo tocó campos ocultos (ej. remember_token) no se registra.
        if ($accion === 'actualizado' && empty($nuevos)) {
            return;
        }

        // Desde comandos de consola (seeders, artisan) no hay petición web ni IP.
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        Auditoria::create([
            'usuario_id' => auth()->id(),
            'accion' => $accion,
            'modelo' => class_basename($modelo),
            'modelo_id' => $modelo->getKey(),
            'valores_anteriores' => $anteriores,
            'valores_nuevos' => $nuevos,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
