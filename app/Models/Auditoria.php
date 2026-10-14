<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * Historial de acciones sobre los datos del sistema.
 * Se llena solo mediante el trait App\Models\Concerns\RegistraAuditoria.
 *
 * created_at es una columna "timestamp" SIN zona que guarda SIEMPRE el instante en UTC, tanto en
 * los registros históricos como en los nuevos, sea cual sea la zona de la aplicación. Así el
 * orden cronológico y la comparación entre registros no dependen de APP_TIMEZONE, y nunca se
 * convierte el histórico (el trigger proteger_auditoria lo impide además).
 * Para filtrar por fecha en SQL, enlazar el valor en UTC ($fecha->utc()).
 */
class Auditoria extends Model
{
    protected $table = 'auditorias';

    public const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id', 'accion', 'modelo', 'modelo_id',
        'valores_anteriores', 'valores_nuevos', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            // created_at se lee con el accesor createdAt() (UTC), no con el cast "datetime".
        ];
    }

    /** El instante de cada registro nuevo se escribe en UTC. */
    public function freshTimestamp()
    {
        return Date::now('UTC');
    }

    /** Se interpreta como UTC y se presenta en la zona de la aplicación. */
    protected function createdAt(): Attribute
    {
        return Attribute::get(fn (?string $valor) => $valor === null
            ? null
            : Date::createFromFormat('Y-m-d H:i:s', substr($valor, 0, 19), 'UTC')->setTimezone(config('app.timezone')));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
