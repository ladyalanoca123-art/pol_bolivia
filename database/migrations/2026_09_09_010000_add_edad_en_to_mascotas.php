<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The backfill fires a deferred constraint trigger. Keep each statement
     * in its own transaction so PostgreSQL evaluates that event before the
     * next ALTER TABLE is attempted.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('ALTER TABLE mascotas ADD COLUMN edad_en VARCHAR(5)');

        // Existing ages were recorded in years. Committing this statement also
        // evaluates trigger_validar_minimo_fotos without weakening that rule.
        DB::statement("UPDATE mascotas SET edad_en = 'años' WHERE edad_en IS NULL");

        DB::statement('ALTER TABLE mascotas ALTER COLUMN edad_en SET NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE mascotas
                ADD CONSTRAINT mascotas_edad_en_valida
                CHECK (edad_en IN ('meses', 'años'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE mascotas
                ADD CONSTRAINT mascotas_edad_y_edad_en_validas
                CHECK (
                    (edad_en = 'meses' AND edad BETWEEN 1 AND 11)
                    OR (edad_en = 'años' AND edad >= 1)
                )
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW mascotas_con_rango AS
            SELECT id, nombre, especie, raza, edad, sexo, color, tamano,
                descripcion, estado, registrado_por, created_at, updated_at,
                CASE
                    WHEN edad_en = 'meses' THEN 'cria'
                    WHEN edad <= 1 THEN 'cria'
                    WHEN edad <= 3 THEN 'joven'
                    WHEN edad <= 7 THEN 'adulto'
                    ELSE 'senior'
                END AS rango_edad
            FROM mascotas
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE mascotas DROP CONSTRAINT IF EXISTS mascotas_edad_y_edad_en_validas');
        DB::statement('ALTER TABLE mascotas DROP CONSTRAINT IF EXISTS mascotas_edad_en_valida');
        DB::statement('ALTER TABLE mascotas DROP COLUMN IF EXISTS edad_en');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW mascotas_con_rango AS
            SELECT id, nombre, especie, raza, edad, sexo, color, tamano,
                descripcion, estado, registrado_por, created_at, updated_at,
                CASE
                    WHEN edad <= 1 THEN 'cria'
                    WHEN edad <= 3 THEN 'joven'
                    WHEN edad <= 7 THEN 'adulto'
                    ELSE 'senior'
                END AS rango_edad
            FROM mascotas
        SQL);
    }
};
