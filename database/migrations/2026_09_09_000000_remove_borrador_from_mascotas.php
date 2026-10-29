<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM mascotas m
                    WHERE m.estado = 'borrador'
                      AND (
                          NOT EXISTS (
                              SELECT 1 FROM fotos_mascota f
                              WHERE f.mascota_id = m.id AND f.tipo = 'foto'
                          )
                          OR NOT EXISTS (
                              SELECT 1 FROM fotos_mascota f
                              WHERE f.mascota_id = m.id
                                AND f.tipo = 'foto'
                                AND f.es_principal = TRUE
                          )
                      )
                ) THEN
                    RAISE EXCEPTION
                        'No se pueden migrar mascotas borrador sin al menos una foto y una foto principal';
                END IF;
            END;
            $$;

            DROP TRIGGER IF EXISTS trigger_validar_minimo_fotos ON mascotas;
            DROP TRIGGER IF EXISTS trigger_validar_multimedia_publicada ON fotos_mascota;

            -- Estas funciones pertenecen a la lógica anterior ya aplicada en
            -- bases existentes. Deben retirarse junto con sus triggers antes
            -- de instalar la validación diferida actual.
            DROP FUNCTION IF EXISTS validar_minimo_fotos();
            DROP FUNCTION IF EXISTS validar_multimedia_mascota_publicada();

            UPDATE mascotas SET estado = 'disponible' WHERE estado = 'borrador';

            ALTER TABLE mascotas DROP CONSTRAINT IF EXISTS mascotas_estado_valido;
            ALTER TABLE mascotas ALTER COLUMN estado SET DEFAULT 'disponible';
            ALTER TABLE mascotas
                ADD CONSTRAINT mascotas_estado_valido
                CHECK (estado IN ('disponible', 'adoptada'));

            -- No se elimina la función actual: CREATE OR REPLACE permite que
            -- esta migración funcione tanto en instalaciones nuevas como existentes.
            CREATE OR REPLACE FUNCTION validar_multimedia_mascota_disponible()
            RETURNS TRIGGER AS $$
            DECLARE
                mascota_a_validar BIGINT;
                estado_mascota VARCHAR(20);
                cantidad_fotos INTEGER;
                cantidad_principales INTEGER;
            BEGIN
                IF TG_TABLE_NAME = 'mascotas' THEN
                    mascota_a_validar := NEW.id;
                ELSE
                    mascota_a_validar := COALESCE(NEW.mascota_id, OLD.mascota_id);
                END IF;

                SELECT estado INTO estado_mascota FROM mascotas WHERE id = mascota_a_validar;

                IF estado_mascota = 'disponible' THEN
                    SELECT COUNT(*) INTO cantidad_fotos
                    FROM fotos_mascota
                    WHERE mascota_id = mascota_a_validar AND tipo = 'foto';

                    SELECT COUNT(*) INTO cantidad_principales
                    FROM fotos_mascota
                    WHERE mascota_id = mascota_a_validar
                      AND tipo = 'foto'
                      AND es_principal = TRUE;

                    IF cantidad_fotos = 0 OR cantidad_principales = 0 THEN
                        RAISE EXCEPTION
                            'Una mascota disponible debe conservar al menos una foto principal';
                    END IF;
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER trigger_validar_minimo_fotos
            AFTER INSERT OR UPDATE ON mascotas
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION validar_multimedia_mascota_disponible();

            CREATE CONSTRAINT TRIGGER trigger_validar_multimedia_publicada
            AFTER INSERT OR UPDATE OR DELETE ON fotos_mascota
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION validar_multimedia_mascota_disponible();
        SQL);
    }

    public function down(): void
    {
        // No se registra qué filas eran borrador antes de convertirlas a disponible.
        // Restaurar el esquema sin restaurar esos datos sería una reversión engañosa.
        throw new LogicException('La eliminación del estado borrador no es reversible automáticamente.');
    }
};
