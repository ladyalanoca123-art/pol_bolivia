<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            // Sin clave foránea a propósito: si un usuario se elimina, su historial se conserva intacto.
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->string('accion', 20);
            $table->string('modelo', 100);
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->jsonb('valores_anteriores')->nullable();
            $table->jsonb('valores_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['modelo', 'modelo_id']);
            $table->index('created_at');
        });

        DB::statement(
            "ALTER TABLE auditorias ADD CONSTRAINT auditorias_accion_valida "
            ."CHECK (accion IN ('creado', 'actualizado', 'eliminado'))"
        );

        // La auditoría solo admite inserciones: nadie puede editar ni borrar el historial.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION proteger_auditoria() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'La auditoría no se puede modificar ni eliminar';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trigger_proteger_auditoria
                BEFORE UPDATE OR DELETE ON auditorias
                FOR EACH ROW EXECUTE FUNCTION proteger_auditoria();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
        DB::unprepared('DROP FUNCTION IF EXISTS proteger_auditoria() CASCADE');
    }
};
