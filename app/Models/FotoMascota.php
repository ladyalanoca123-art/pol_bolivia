<?php

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use App\Services\ImagenSegura;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FotoMascota extends Model
{
    use HasFactory, RegistraAuditoria;

    protected $table = 'fotos_mascota';

    /** Máximo de fotos por mascota: el mismo que exige validar_limite_multimedia() en PostgreSQL. */
    public const MAXIMO_POR_MASCOTA = 4;

    public const MENSAJE_LIMITE = 'La mascota ya tiene 4 fotos, el máximo permitido. Elimina una antes de agregar otra.';

    public $timestamps = false;

    protected $fillable = [
        'mascota_id', 'tipo', 'url', 'es_principal', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'orden' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * URL de la miniatura (App\Services\ImagenSegura::rutaMiniatura), o de la imagen completa si la foto
     * todavía no tiene miniatura (fotos anteriores, hasta ejecutar pol:generar-miniaturas).
     */
    protected function urlMiniatura(): Attribute
    {
        return Attribute::get(function (): string {
            $almacen = Storage::disk('public');
            $miniatura = ImagenSegura::rutaMiniatura($this->url);

            return $almacen->url($almacen->exists($miniatura) ? $miniatura : $this->url);
        });
    }

    public function mascota(): BelongsTo
    {
        return $this->belongsTo(Mascota::class, 'mascota_id');
    }
}