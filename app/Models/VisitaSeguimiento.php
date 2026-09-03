<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitaSeguimiento extends Model
{
    use HasFactory;

    protected $table = 'visitas_seguimiento';

    protected $fillable = [
        'seguimiento_id', 'voluntario_id', 'fecha_programada',
        'fecha_realizada', 'observaciones', 'estado_salud',
        'foto_url', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_realizada' => 'datetime',
        ];
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoAdopcion::class, 'seguimiento_id');
    }

    public function voluntario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voluntario_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'visita_id');
    }
}