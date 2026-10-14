<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Al aprobar una postulación de un voluntario que ya tenía perfil (reactivación),
     * si la nueva postulación no trae habilidades (pregunta 8 opcional vacía),
     * se conservan las habilidades que ya tenía el perfil en lugar de borrarlas.
     *
     * Único cambio respecto a database/sql/pol_bolivia.sql:
     *   habilidades = NEW.habilidades
     *   →  habilidades = COALESCE(NULLIF(btrim(NEW.habilidades), ''), habilidades)
     * El resto de la regla (rol, estado, postulacion_id, disponibilidad, zona, alta nueva) no cambia.
     */
    public function up(): void
    {
        DB::unprepared($this->funcion("COALESCE(NULLIF(btrim(NEW.habilidades), ''), habilidades)"));
    }

    /** Restaura la regla original: las habilidades se reemplazan siempre, aunque vengan vacías. */
    public function down(): void
    {
        DB::unprepared($this->funcion('NEW.habilidades'));
    }

    private function funcion(string $habilidades): string
    {
        return <<<SQL
            CREATE OR REPLACE FUNCTION procesar_aprobacion_postulacion()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF NEW.estado = 'aprobada' AND OLD.estado <> 'aprobada' THEN

                    UPDATE usuarios
                    SET rol = 'voluntario'
                    WHERE id = NEW.usuario_id;

                    IF EXISTS (SELECT 1 FROM voluntarios WHERE usuario_id = NEW.usuario_id) THEN
                        UPDATE voluntarios
                        SET estado = 'activo',
                            postulacion_id = NEW.id,
                            habilidades = {$habilidades},
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
            \$\$ LANGUAGE plpgsql;
        SQL;
    }
};
