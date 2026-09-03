<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mascota extends Model
{
    use HasFactory;

    protected $table = 'mascotas';

    protected $fillable = [
        'nombre', 'especie', 'raza', 'edad', 'sexo',
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
}