<?php

namespace App\Console\Commands;

use App\Models\FotoMascota;
use App\Services\ImagenNoProcesada;
use App\Services\ImagenSegura;
use Illuminate\Console\Command;
use League\Flysystem\PathTraversalDetected;

/**
 * Genera las miniaturas que faltan de las fotos de mascotas ya guardadas (App\Services\ImagenSegura).
 *
 * Es idempotente: omite las fotos que ya tienen una miniatura válida. No modifica la base de datos
 * ni las imágenes originales; solo escribe archivos en "miniaturas/". Con --simular no escribe nada.
 */
class GenerarMiniaturas extends Command
{
    protected $signature = 'pol:generar-miniaturas
        {--simular : Muestra qué se generaría sin escribir ningún archivo}';

    protected $description = 'Genera las miniaturas que faltan de las fotos de mascotas, sin tocar los originales ni la base de datos';

    public function handle(ImagenSegura $imagenSegura): int
    {
        $simular = (bool) $this->option('simular');
        $resumen = ['procesada' => 0, 'omitida' => 0, 'fallida' => 0];
        $fallos = [];

        FotoMascota::query()->where('tipo', 'foto')->orderBy('id')->chunkById(100, function ($fotos) use ($imagenSegura, $simular, &$resumen, &$fallos): void {
            foreach ($fotos as $foto) {
                try {
                    $resumen[$imagenSegura->generarMiniatura($foto->url, simular: $simular)]++;
                } catch (ImagenNoProcesada $exception) {
                    $resumen['fallida']++;
                    $fallos[] = [$foto->id, $foto->mascota_id, $exception->getMessage()];
                } catch (PathTraversalDetected) {
                    // Ruta guardada que sale del disco (p. ej. con "../"): se informa sin mostrarla y se sigue.
                    $resumen['fallida']++;
                    $fallos[] = [$foto->id, $foto->mascota_id, 'La ruta de la imagen no es válida.'];
                }
            }
        });

        $this->info($simular ? 'SIMULACIÓN: no se escribió ningún archivo.' : 'Miniaturas de las fotos de mascotas.');
        $this->table(['Resultado', 'Fotos'], [
            [$simular ? 'Por generar' : 'Generadas', $resumen['procesada']],
            ['Omitidas (ya tenían miniatura válida)', $resumen['omitida']],
            ['Fallidas', $resumen['fallida']],
        ]);

        if ($fallos !== []) {
            $this->warn('Fotos que no se pudieron procesar (la foto original no se modificó):');
            $this->table(['Foto', 'Mascota', 'Motivo'], $fallos);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
