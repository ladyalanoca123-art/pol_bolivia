<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cuándo y cómo cada persona aceptó los Términos y condiciones y la Política de privacidad.
 * Una fila por documento y versión. Las filas no se pueden modificar (son la constancia de la aceptación);
 * si la persona elimina su cuenta, sus aceptaciones se borran con ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aceptaciones_legales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('documento', 20);
            $table->string('version', 20);
            // registro: al crear la cuenta; actualizacion: al aceptar una versión nueva al iniciar sesión.
            $table->string('origen', 20);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('aceptado_at')->useCurrent();

            $table->unique(['usuario_id', 'documento', 'version']);
        });

        DB::statement(
            'ALTER TABLE aceptaciones_legales ADD CONSTRAINT aceptaciones_legales_documento_valido '
            ."CHECK (documento IN ('terminos', 'privacidad'))"
        );
        DB::statement(
            'ALTER TABLE aceptaciones_legales ADD CONSTRAINT aceptaciones_legales_origen_valido '
            ."CHECK (origen IN ('registro', 'actualizacion'))"
        );

        // Una aceptación no se corrige: si cambia el documento, se acepta una versión nueva.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION proteger_aceptacion_legal() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Una aceptación legal registrada no se puede modificar';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER aceptaciones_legales_sin_cambios
                BEFORE UPDATE ON aceptaciones_legales
                FOR EACH ROW EXECUTE FUNCTION proteger_aceptacion_legal();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('aceptaciones_legales');
        DB::unprepared('DROP FUNCTION IF EXISTS proteger_aceptacion_legal()');
    }
};
