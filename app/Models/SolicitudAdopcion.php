<?php

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudAdopcion extends Model
{
    use HasFactory, RegistraAuditoria;

    protected $table = 'solicitudes_adopcion';

    /** Nombres que ven las personas. "en_espera" es la etapa de entrevista o visita. */
    public const ESTADOS_LEGIBLES = [
        'pendiente' => 'Pendiente',
        'en_espera' => 'En evaluación',
        'aceptada' => 'Aceptada',
        'rechazada' => 'Rechazada',
        'cancelada' => 'Cancelada',
        'cerrada' => 'Cerrada',
    ];

    public const NIVELES_ALERTA = [
        'rojo' => 'Condición incompatible',
        'amarillo' => 'Requiere revisión',
        'verde' => 'Sin observaciones',
    ];

    protected $fillable = [
        'solicitante_id', 'mascota_id', 'estado',
        'tiene_otras_mascotas', 'viven_otras_personas',
        'zona_aproximada', 'horario_preferido',
        'declara_informacion_veraz', 'comentarios_admin',
        'respuestas', 'version_cuestionario',
        'nivel_alerta', 'alertas', 'version_reglas',
    ];

    protected function casts(): array
    {
        return [
            'tiene_otras_mascotas' => 'boolean',
            'viven_otras_personas' => 'boolean',
            'declara_informacion_veraz' => 'boolean',
            'respuestas' => 'array',
            'alertas' => 'array',
        ];
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS_LEGIBLES[$this->estado] ?? ucfirst(str_replace('_', ' ', $this->estado));
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class, 'mascota_id');
    }

    public function seguimiento(): HasOne
    {
        return $this->hasOne(SeguimientoAdopcion::class, 'solicitud_id');
    }
}
