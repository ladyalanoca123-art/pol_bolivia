<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incidencia extends Model
{
    use HasFactory;

    protected $table = 'incidencias';

    protected $fillable = [
        'seguimiento_id', 'visita_id', 'reportado_por',
        'tipo', 'descripcion', 'estado', 'comentarios_resolucion',
        'atendida_por', 'atendida_at',
    ];

    protected function casts(): array
    {
        return [
            'atendida_at' => 'datetime',
        ];
    }

    public function seguimiento(): BelongsTo
    {
        return $this->belongsTo(SeguimientoAdopcion::class, 'seguimiento_id');
    }

    public function visita(): BelongsTo
    {
        return $this->belongsTo(VisitaSeguimiento::class, 'visita_id');
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por');
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }
}
