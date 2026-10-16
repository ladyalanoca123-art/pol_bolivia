<?php

namespace App\Models;

use App\Models\Concerns\RegistraAuditoria;
use App\Services\ImagenSegura;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\QueryException;
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

    /**
     * Si el error es el de validar_limite_multimedia() por exceso de fotos (RAISE EXCEPTION, SQLSTATE P0001,
     * "Una mascota no puede tener más de 4 fotos"); el de video ("más de 1 video") no cuenta.
     */
    public static function esErrorDeLimiteDeFotos(QueryException $exception): bool
    {
        return $exception->getCode() === 'P0001'
            && str_contains($exception->getMessage(), 'no puede tener más de '.self::MAXIMO_POR_MASCOTA.' fotos');
    }

    /** Mensaje cuando una selección de fotos no cabe en los $libres espacios que quedan. */
    public static function mensajeEspaciosLibres(int $libres): string
    {
        return $libres <= 0
            ? self::MENSAJE_LIMITE
            : 'Solo puedes agregar '.($libres === 1 ? '1 foto más' : "{$libres} fotos más").' a esta mascota.';
    }

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