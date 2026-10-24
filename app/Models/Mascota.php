<?php

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mascota extends Model
{
    use HasFactory, RegistraAuditoria;

    protected $table = 'mascotas';

    /** reservada: tiene una solicitud aprobada y salió del catálogo. Solo PostgreSQL asigna reservada y adoptada. */
    public const ESTADOS_LEGIBLES = [
        'disponible' => 'Disponible',
        'reservada' => 'Reservada',
        'adoptada' => 'Adoptada',
    ];

    protected $fillable = [
        'nombre', 'especie', 'raza', 'edad', 'edad_en', 'sexo',
        'color', 'tamano', 'descripcion', 'estado',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'edad' => 'integer',
        ];
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoMascota::class, 'mascota_id');
    }

    public function solicitudesAdopcion(): HasMany
    {
        return $this->hasMany(SolicitudAdopcion::class, 'mascota_id');
    }

    /** La solicitud aprobada que reserva a la mascota (como máximo una: idx_una_solicitud_aprobada_por_mascota). */
    public function solicitudAprobada(): HasOne
    {
        return $this->hasOne(SolicitudAdopcion::class, 'mascota_id')->where('estado', 'aprobada');
    }

    public function estadoLegible(): string
    {
        return self::ESTADOS_LEGIBLES[$this->estado] ?? ucfirst($this->estado);
    }

    public function getEdadFormateadaAttribute(): string
    {
        if ($this->edad_en === 'meses') {
            return sprintf('%d %s', $this->edad, $this->edad === 1 ? 'mes' : 'meses');
        }

        return sprintf('%d %s', $this->edad, $this->edad === 1 ? 'año' : 'años');
    }
}
