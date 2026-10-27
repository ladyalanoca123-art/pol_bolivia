<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Liga la secuencia del número de acta a su columna. Así PostgreSQL la elimina junto con la tabla o la
 * columna, y no queda una secuencia suelta que impida volver a crear el esquema. No cambia su valor actual:
 * los números de acta siguientes continúan donde iban.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER SEQUENCE seq_numero_acta_adopcion OWNED BY solicitudes_adopcion.numero_acta');
    }

    public function down(): void
    {
        DB::statement('ALTER SEQUENCE IF EXISTS seq_numero_acta_adopcion OWNED BY NONE');
    }
};
