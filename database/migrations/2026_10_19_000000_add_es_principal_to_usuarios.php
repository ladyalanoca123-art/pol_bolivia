<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Administrador principal: una sola persona en todo el sistema.
     *
     * - Columna es_principal (FALSE para todas las cuentas existentes: no se borra ni se cambia ningún dato).
     * - Índice único parcial: como mucho una fila con es_principal = TRUE.
     * - Trigger que protege al principal en PostgreSQL, como los voluntarios: no se puede eliminar, y mientras
     *   tenga la marca debe ser un administrador con la cuenta activa (no se le puede quitar el rol ni desactivar).
     *   Para retirarlo, primero se transfiere la marca a otro administrador (pol:transferir-principal).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE usuarios ADD COLUMN es_principal BOOLEAN NOT NULL DEFAULT FALSE');
        DB::statement('CREATE UNIQUE INDEX idx_un_administrador_principal ON usuarios (es_principal) WHERE es_principal');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION proteger_administrador_principal()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.es_principal THEN
                        RAISE EXCEPTION
                            'No se puede eliminar al administrador principal: primero transfiere ese rol a otro administrador';
                    END IF;

                    RETURN OLD;
                END IF;

                IF NEW.es_principal AND (NEW.rol <> 'administrador' OR NEW.estado = FALSE) THEN
                    RAISE EXCEPTION
                        'El administrador principal debe ser un administrador con la cuenta activa';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trigger_proteger_administrador_principal
            BEFORE INSERT OR UPDATE OF rol, estado, es_principal OR DELETE ON usuarios
            FOR EACH ROW EXECUTE FUNCTION proteger_administrador_principal();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trigger_proteger_administrador_principal ON usuarios;
            DROP FUNCTION IF EXISTS proteger_administrador_principal();
            DROP INDEX IF EXISTS idx_un_administrador_principal;
            ALTER TABLE usuarios DROP COLUMN IF EXISTS es_principal;
        SQL);
    }
};
