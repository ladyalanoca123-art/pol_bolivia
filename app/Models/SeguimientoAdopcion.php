<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeguimientoAdopcion extends Model
{
    use HasFactory;

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
}