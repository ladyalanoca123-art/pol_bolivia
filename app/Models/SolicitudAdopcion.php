<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudAdopcion extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_adopcion';

    protected $fillable = [
        'solicitante_id', 'mascota_id', 'estado',
        'tiene_otras_mascotas', 'viven_otras_personas',
        'tiene_espacio_adecuado', 'zona_aproximada',
        'horario_preferido', 'comentarios_solicitante',
        'declara_informacion_veraz', 'comentarios_admin',
    ];

    protected function casts(): array
    {
        return [
            'tiene_otras_mascotas' => 'boolean',
            'viven_otras_personas' => 'boolean',
            'tiene_espacio_adecuado' => 'boolean',
            'declara_informacion_veraz' => 'boolean',
        ];
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