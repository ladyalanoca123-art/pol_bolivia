<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PostulacionVoluntariado extends Model
{
    use HasFactory;

    protected $table = 'postulaciones_voluntariado';

    protected $fillable = [
        'usuario_id', 'motivacion', 'habilidades',
        'disponibilidad', 'zona_preferencia', 'estado',
        'comentarios_admin', 'revisado_por', 'revisado_at',
    ];

    protected function casts(): array
    {
        return [
            'revisado_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function voluntario(): HasOne
    {
        return $this->hasOne(Voluntario::class, 'postulacion_id');
    }
}