<?php

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeguimientoAdopcion extends Model
{
    use HasFactory, RegistraAuditoria;

    protected $table = 'seguimientos_adopcion';

    protected $fillable = [
        'solicitud_id', 'voluntario_id', 'estado',
        'zona_aproximada', 'horario_preferido',
        'fecha_inicio', 'fecha_cierre', 'observaciones_cierre',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudAdopcion::class, 'solicitud_id');
    }

    public function voluntario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voluntario_id');
    }

    public function visitas(): HasMany
    {
        return $this->hasMany(VisitaSeguimiento::class, 'seguimiento_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'seguimiento_id');
    }

    /**
     * Lo que falta para poder cerrarlo, en frases para el administrador. Vacío si se puede cerrar.
     * Mismas condiciones que exige PostgreSQL (validar_cierre_seguimiento): ninguna visita programada pendiente
     * y ninguna incidencia sin atender.
     *
     * @return list<string>
     */
    public function pendientesParaCerrar(): array
    {
        $visitas = $this->visitas()->where('estado', 'programada')->orderBy('fecha_programada')->pluck('fecha_programada');
        $incidencias = $this->incidencias()->where('estado', '<>', 'atendida')->count();
        $pendientes = [];

        if ($incidencias > 0) {
            $pendientes[] = $incidencias === 1 ? 'Atiende 1 incidencia pendiente.' : "Atiende {$incidencias} incidencias pendientes.";
        }

        foreach ($visitas as $fecha) {
            $pendientes[] = 'Registra la visita programada del '.$fecha->format('d/m/Y').' (realizada o «No asistió»).';
        }

        return $pendientes;
    }
}