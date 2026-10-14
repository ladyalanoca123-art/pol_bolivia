<?php

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PostulacionVoluntariado extends Model
{
    use HasFactory, RegistraAuditoria;

    protected $table = 'postulaciones_voluntariado';

    protected $fillable = [
        'usuario_id', 'motivacion', 'habilidades',
        'disponibilidad', 'zona_preferencia', 'estado',
        'comentarios_admin', 'revisado_por', 'revisado_at',
        'experiencia_previa', 'experiencia_detalle', 'areas_interes',
        'dias_disponibles', 'franjas_horarias', 'confirma_mayor_edad',
        'declara_informacion_veraz', 'version_cuestionario',
    ];

    protected function casts(): array
    {
        return [
            'revisado_at' => 'datetime',
            'experiencia_previa' => 'boolean',
            'areas_interes' => 'array',
            'dias_disponibles' => 'array',
            'franjas_horarias' => 'array',
            'confirma_mayor_edad' => 'boolean',
            'declara_informacion_veraz' => 'boolean',
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