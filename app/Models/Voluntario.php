<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voluntario extends Model
{
    use HasFactory;

    protected $table = 'voluntarios';

    protected $fillable = [
        'usuario_id', 'postulacion_id', 'habilidades',
        'disponibilidad', 'zona_preferencia', 'estado',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(
            PostulacionVoluntariado::class,
            'postulacion_id'
        );
    }
}
