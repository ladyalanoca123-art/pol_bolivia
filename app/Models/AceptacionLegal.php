<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de que una persona aceptó un documento legal (terminos o privacidad) en una versión dada.
 * Solo se crean; PostgreSQL impide modificarlas (trigger aceptaciones_legales_sin_cambios).
 */
class AceptacionLegal extends Model
{
    protected $table = 'aceptaciones_legales';

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'documento', 'version', 'origen', 'ip', 'user_agent', 'aceptado_at'];

    protected function casts(): array
    {
        return ['aceptado_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
