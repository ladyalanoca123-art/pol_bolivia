<aside x-data="{ open: false }" @admin-menu-toggle.window="open = !open" class="z-40">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 bg-slate-950/50 md:hidden" @click="open = false" aria-hidden="true"></div>

    <div :class="open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-blue-950 text-white shadow-2xl transition-transform duration-200 ease-out md:w-64">
        <div class="flex h-20 shrink-0 items-center justify-between border-b border-white/10 px-5">
            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-3" aria-label="POL-Bolivia, panel principal">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white p-1.5">
                    <img src="{{ asset('images/pol-bolivia/logo.png') }}" alt="" class="max-h-full max-w-full object-contain">
                </span>
                <span class="min-w-0"><span class="block truncate text-base font-bold tracking-tight">POL-Bolivia</span><span class="mt-0.5 block text-[11px] font-medium uppercase tracking-[.16em] text-blue-200">Administración</span></span>
            </a>
            <button type="button" @click="open = false" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-blue-100 hover:bg-white/10 md:hidden" aria-label="Cerrar menú">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Navegación administrativa">
            <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[.18em] text-blue-300">Principal</p>
            @php
                $items = [
                    ['admin.dashboard', 'Panel principal', 'admin.dashboard', 'M4 6h16M4 12h16M4 18h16'],
                    ['admin.solicitudes-adopcion.index', 'Solicitudes', 'admin.solicitudes-adopcion.*', 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
                    ['admin.seguimientos.index', 'Seguimientos', 'admin.seguimientos.*', 'M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['admin.mascotas.index', 'Mascotas', 'admin.mascotas.*', 'M12 21s-8-4.4-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.6-8 11-8 11Z'],
                ];
            @endphp
            @foreach ($items as [$routeName, $label, $activePattern, $icon])
                <a href="{{ route($routeName) }}" @click="open = false" @class(['group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition', 'bg-amber-400 text-blue-950 shadow-sm' => request()->routeIs($activePattern), 'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs($activePattern)])>
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                    <span>{{ $label }}</span>
                    @if ($routeName === 'admin.dashboard' && request()->routeIs('admin.dashboard'))<span class="ms-auto h-1.5 w-1.5 rounded-full bg-blue-950"></span>@endif
                </a>
            @endforeach

            <p class="px-3 pb-2 pt-6 text-[10px] font-bold uppercase tracking-[.18em] text-blue-300">Gestión</p>
            @php
                $items = [
                    ['admin.postulaciones-voluntariado.index', 'Postulaciones', 'admin.postulaciones-voluntariado.*', 'M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z'],
                    ['admin.voluntarios.index', 'Voluntarios', 'admin.voluntarios.*', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m16-11a4 4 0 0 1 0 8m-9-8a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                    ['admin.usuarios.index', 'Usuarios', 'admin.usuarios.*', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m16-11a4 4 0 0 1 0 8m-9-8a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
                    ['admin.reportes.index', 'Reportes', 'admin.reportes.*', 'M3 3v18h18M8 15l4-4 4 4 5-7'],
                    ['admin.auditoria.index', 'Auditoría', 'admin.auditoria.*', 'M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Zm-3 9 2 2 4-4'],
                ];
            @endphp
            @foreach ($items as [$routeName, $label, $activePattern, $icon])
                <a href="{{ route($routeName) }}" @click="open = false" @class(['group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition', 'bg-amber-400 text-blue-950 shadow-sm' => request()->routeIs($activePattern), 'text-blue-100 hover:bg-white/10 hover:text-white' => ! request()->routeIs($activePattern)])>
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-2xl bg-white/[.07] p-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-400 text-sm font-bold text-blue-950">{{ mb_strtoupper(mb_substr(auth()->user()->nombres ?? 'A', 0, 1)) }}</span>
                <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ auth()->user()->nombres }} {{ auth()->user()->apellidos }}</p><p class="truncate text-xs text-blue-200">Administración</p></div>
            </div>
        </div>
    </div>
</aside>
