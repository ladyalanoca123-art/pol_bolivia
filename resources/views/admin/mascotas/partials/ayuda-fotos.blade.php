{{--
    Ayuda de un selector de fotos y, dentro de un formulario con x-data="optimizadorFotos(...)",
    el resultado de la optimización en el navegador y los avisos del campo $campo. $ayuda = false omite el texto de ayuda.
--}}
@if ($ayuda ?? true)
    <p class="text-xs text-gray-500">JPG, PNG o WEBP · máximo {{ \App\Http\Requests\Admin\RegisterMascotaRequest::megasMaximos() }} MB por foto · mínimo {{ \App\Services\ImagenSegura::LADO_MINIMO }} × {{ \App\Services\ImagenSegura::LADO_MINIMO }} px · hasta {{ \App\Models\FotoMascota::MAXIMO_POR_MASCOTA }} fotos por mascota. Las fotos grandes se optimizan automáticamente antes de subir.</p>
@endif
<p x-cloak x-show="optimizando(@js($campo))" class="text-xs font-medium text-blue-800" role="status">Optimizando fotos…</p>
<ul x-cloak x-show="lineas(@js($campo)).length" class="space-y-0.5 text-xs text-gray-600" aria-live="polite">
    <template x-for="linea in lineas(@js($campo))" :key="linea"><li x-text="linea"></li></template>
</ul>
<ul x-cloak x-show="avisos(@js($campo)).length" class="space-y-0.5 text-sm text-red-600" role="alert">
    <template x-for="aviso in avisos(@js($campo))" :key="aviso"><li x-text="aviso"></li></template>
</ul>
