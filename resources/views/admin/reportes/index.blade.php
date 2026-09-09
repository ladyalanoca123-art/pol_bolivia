<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Reportes y estadísticas</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
        <div class="p-6 bg-white shadow-sm sm:rounded-lg">
            <h3 class="text-lg font-semibold">Período de adopciones</h3>
            <form method="GET" action="{{ route('admin.reportes.index') }}" class="flex flex-wrap items-end gap-4 mt-4">
                <div><label class="block text-sm font-medium" for="fecha_inicio">Fecha inicial</label><input id="fecha_inicio" type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $periodo['fecha_inicio'] ?? '') }}" class="rounded border-gray-300">@error('fecha_inicio')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="block text-sm font-medium" for="fecha_fin">Fecha final</label><input id="fecha_fin" type="date" name="fecha_fin" value="{{ old('fecha_fin', $periodo['fecha_fin'] ?? '') }}" class="rounded border-gray-300">@error('fecha_fin')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <button class="text-indigo-600">Aplicar filtro</button><a class="text-gray-600" href="{{ route('admin.reportes.index') }}">Limpiar</a>
            </form>
        </div>

        <section><h3 class="mb-3 text-lg font-semibold">Indicadores generales</h3><div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-report-card title="Total mascotas" :value="$totalMascotas" />
            <x-report-card title="Mascotas disponibles" :value="$mascotasPorEstado['disponible']" />
            <x-report-card title="Mascotas adoptadas" :value="$mascotasPorEstado['adoptada']" />
            <x-report-card title="Total solicitudes" :value="$totalSolicitudes" />
            <x-report-card title="Adopciones realizadas" :value="$totalAdopciones" />
            <x-report-card title="Voluntarios activos" :value="$voluntariosPorEstado['activo']" />
            <x-report-card title="Seguimientos activos" :value="$seguimientosPorEstado['activo']" />
            <x-report-card title="Incidencias pendientes" :value="$incidenciasPorEstado['pendiente']" />
        </div></section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-report-section title="Mascotas" :rows="['Total' => $totalMascotas, 'Disponibles' => $mascotasPorEstado['disponible'], 'Adoptadas' => $mascotasPorEstado['adoptada'], 'Perros' => $mascotasPorEspecie['perro'], 'Gatos' => $mascotasPorEspecie['gato']]" chart-id="mascotas-chart" />
            <x-report-section title="Solicitudes de adopción" :rows="['Total' => $totalSolicitudes, 'Pendientes' => $solicitudesPorEstado['pendiente'], 'En espera' => $solicitudesPorEstado['en_espera'], 'Aceptadas' => $solicitudesPorEstado['aceptada'], 'Rechazadas' => $solicitudesPorEstado['rechazada'], 'Canceladas' => $solicitudesPorEstado['cancelada'], 'Cerradas' => $solicitudesPorEstado['cerrada']]" chart-id="solicitudes-chart" />
            <x-report-section title="Voluntarios y postulaciones" :rows="['Total voluntarios' => $totalVoluntarios, 'Activos' => $voluntariosPorEstado['activo'], 'Inactivos' => $voluntariosPorEstado['inactivo'], 'Postulaciones pendientes' => $postulacionesPorEstado['pendiente'], 'Postulaciones aprobadas' => $postulacionesPorEstado['aprobada'], 'Postulaciones rechazadas' => $postulacionesPorEstado['rechazada'], 'Postulaciones canceladas' => $postulacionesPorEstado['cancelada']]" />
            <x-report-section title="Seguimientos" :rows="['Total' => $totalSeguimientos, 'Sin asignar' => $seguimientosPorEstado['sin_asignar'], 'Activos' => $seguimientosPorEstado['activo'], 'Cerrados' => $seguimientosPorEstado['cerrado']]" />
            <x-report-section title="Incidencias" :rows="['Total' => $totalIncidencias, 'Pendientes' => $incidenciasPorEstado['pendiente'], 'En proceso' => $incidenciasPorEstado['en_proceso'], 'Atendidas' => $incidenciasPorEstado['atendida']]" chart-id="incidencias-chart" />
            <x-report-section title="Evolución de adopciones" :rows="['Adopciones en el período' => $totalAdopciones]" chart-id="adopciones-chart" />
        </div>
    </div></div>

    <script>
        const reportCharts = {
            'mascotas-chart': @json($mascotasPorEspecie),
            'solicitudes-chart': @json($solicitudesPorEstado),
            'incidencias-chart': @json($incidenciasPorEstado),
            'adopciones-chart': @json($adopcionesPorFecha),
        };
        Object.entries(reportCharts).forEach(([id, data]) => {
            const canvas = document.getElementById(id);
            if (!canvas) return;
            const context = canvas.getContext('2d');
            const entries = Object.entries(data);
            const width = canvas.width = canvas.offsetWidth * devicePixelRatio;
            const height = canvas.height = 230 * devicePixelRatio;
            context.scale(devicePixelRatio, devicePixelRatio);
            const displayWidth = canvas.offsetWidth;
            const maximum = Math.max(...entries.map(([, value]) => value), 1);
            const barWidth = Math.max(18, (displayWidth - 40) / Math.max(entries.length, 1) - 14);
            entries.forEach(([label, value], index) => {
                const x = 25 + index * (barWidth + 14);
                const barHeight = (value / maximum) * 150;
                context.fillStyle = '#4f46e5';
                context.fillRect(x, 180 - barHeight, barWidth, barHeight);
                context.fillStyle = '#111827'; context.font = '12px sans-serif';
                context.fillText(String(value), x, 170 - barHeight);
                context.save(); context.translate(x + barWidth / 2, 212); context.rotate(-0.45);
                context.fillText(label.replaceAll('_', ' '), 0, 0); context.restore();
            });
        });
    </script>
</x-app-layout>
