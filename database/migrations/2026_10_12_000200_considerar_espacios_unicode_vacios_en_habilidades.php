<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Amplía la corrección de 2026_10_12_000100: al reactivar un voluntario, las habilidades
     * nuevas se consideran vacías (y se conservan las anteriores) si solo contienen espacios
     * en blanco de cualquier tipo: espacios, tabuladores, saltos de línea, espacio duro (U+00A0),
     * espacio de ancho cero (U+200B), marca de dirección (U+200E) o BOM (U+FEFF).
     * Son los mismos caracteres que limpia Laravel en los formularios (TrimStrings), así que
     * la regla da el mismo resultado aunque la postulación se inserte directamente en PostgreSQL.
     *
     * Único cambio respecto a la versión instalada por 000100:
     *   COALESCE(NULLIF(btrim(NEW.habilidades), ''), habilidades)
     *   →  COALESCE(NULLIF(regexp_replace(NEW.habilidades, <patrón>, '', 'g'), ''), habilidades)
     */
    private const PATRON = '^[\s\u00A0\u200B\u200E\uFEFF]+|[\s\u00A0\u200B\u200E\uFEFF]+$';

    public function up(): void
    {
        DB::unprepared($this->funcion("COALESCE(NULLIF(regexp_replace(NEW.habilidades, '" . self::PATRON . "', '', 'g'), ''), habilidades)"));
    }

    /** Vuelve a la versión de 2026_10_12_000100 (btrim, que solo quita espacios normales). */
    public function down(): void
    {
        DB::unprepared($this->funcion("COALESCE(NULLIF(btrim(NEW.habilidades), ''), habilidades)"));
    }

    private function funcion(string $habilidades): string
    {
        $sql = <<<'SQL'
            CREATE OR REPLACE FUNCTION procesar_aprobacion_postulacion()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.estado = 'aprobada' AND OLD.estado <> 'aprobada' THEN

                    UPDATE usuarios
                    SET rol = 'voluntario'
                    WHERE id = NEW.usuario_id;

                    IF EXISTS (SELECT 1 FROM voluntarios WHERE usuario_id = NEW.usuario_id) THEN
                        UPDATE voluntarios
                        SET estado = 'activo',
                            postulacion_id = NEW.id,
                            habilidades = __HABILIDADES__,
                            disponibilidad = NEW.disponibilidad,
                            zona_preferencia = NEW.zona_preferencia,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE usuario_id = NEW.usuario_id;
                    ELSE
                        INSERT INTO voluntarios (usuario_id, postulacion_id, habilidades, disponibilidad, zona_preferencia)
                        VALUES (NEW.usuario_id, NEW.id, NEW.habilidades, NEW.disponibilidad, NEW.zona_preferencia);
                    END IF;

                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL;

        return str_replace('__HABILIDADES__', $habilidades, $sql);
    }
};
