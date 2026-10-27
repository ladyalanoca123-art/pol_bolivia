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

    /**
     * Nombres que ven las personas, en el orden del flujo:
     * Recibida → En evaluación (revisión y contacto externo) → Reservada (aprobada, esperando la entrega) → Adopción concretada.
     */
    public const ESTADOS_LEGIBLES = [
        'pendiente' => 'Recibida',
        'en_espera' => 'En evaluación',
        // Internamente sigue siendo aprobada: la mascota queda reservada hasta registrar la entrega.
        'aprobada' => 'Reservada',
        'aceptada' => 'Adopción concretada',
        'rechazada' => 'Rechazada',
        'no_concretada' => 'Entrega no concretada',
        'cancelada' => 'Cancelada',
        'cerrada' => 'Cerrada',
    ];

    /** Estados en trámite. PostgreSQL limita a MAX_ACTIVAS por ciudadano (validar_limite_solicitudes_activas). */
    public const ESTADOS_ACTIVOS = ['pendiente', 'en_espera', 'aprobada'];

    public const MAX_ACTIVAS = 2;

    /** Mismo texto que el error de PostgreSQL. */
    public const LIMITE_ALCANZADO = 'Ya tienes 2 solicitudes de adopción activas. Para enviar otra, espera la respuesta o cancela una de ellas.';

    /** Etapas de la línea de progreso, en orden. */
    public const ETAPAS = [1 => 'pendiente', 2 => 'en_espera', 3 => 'aprobada', 4 => 'aceptada'];

    public const NIVELES_ALERTA = [
        'rojo' => 'Condición incompatible',
        'amarillo' => 'Requiere revisión',
        'verde' => 'Sin observaciones',
    ];

    // Los datos de la evaluación, la aprobación y la entrega no son asignables en masa: los controladores
    // los guardan con forceFill() después de validar y autorizar cada acción.
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
            // La registra PostgreSQL (trigger_registrar_resolucion_solicitud) al pasar a un estado resuelto.
            'resuelta_at' => 'datetime',
            // aprobada_at y numero_acta los asigna PostgreSQL al aprobar y no cambian después.
            'aprobada_at' => 'datetime',
            'entregada_at' => 'datetime',
            'compromiso_firmado' => 'boolean',
        ];
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS_LEGIBLES[$this->estado] ?? ucfirst(str_replace('_', ' ', $this->estado));
    }

    public function estaActiva(): bool
    {
        return in_array($this->estado, self::ESTADOS_ACTIVOS, true);
    }

    /** Si terminó sin concretarse la adopción. */
    public function terminoSinAdopcion(): bool
    {
        return in_array($this->estado, ['rechazada', 'cancelada', 'cerrada', 'no_concretada'], true);
    }

    /**
     * Última etapa alcanzada (1 a 4) para la línea de progreso. En un estado final sin adopción es la etapa en
     * la que se detuvo: «Reservada» si tiene fecha de aprobación; «En evaluación» si la auditoría registra que
     * terminó desde esa etapa.
     */
    public function etapaAlcanzada(): int
    {
        return match (true) {
            $this->estado === 'aceptada' => 4,
            $this->estado === 'aprobada' || $this->aprobada_at !== null => 3,
            $this->estado === 'en_espera' => 2,
            $this->estado === 'pendiente' => 1,
            default => $this->estadoAnteriorAlFinal() === 'en_espera' ? 2 : 1,
        };
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

    /** Estado desde el que pasó a su estado final, según la auditoría (cerrada y las cancelaciones automáticas no tienen). */
    private function estadoAnteriorAlFinal(): ?string
    {
        return Auditoria::query()
            ->where('modelo', 'SolicitudAdopcion')
            ->where('modelo_id', $this->id)
            ->whereRaw("valores_nuevos ->> 'estado' = ?", [$this->estado])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->selectRaw("valores_anteriores ->> 'estado' as anterior")
            ->value('anterior');
    }
}
