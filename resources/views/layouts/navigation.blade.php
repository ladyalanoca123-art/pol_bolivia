{{-- Administrador: 9 secciones; el menú horizontal se muestra desde xl y, por debajo, el menú responsive. --}}
@php($esAdmin = auth()->user()->hasRole('administrador'))
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    {{-- Ciudadano: portada pública. Administrador y voluntario: mismos destinos que antes. --}}
                    <a href="{{ auth()->user()->hasRole('ciudadano') ? route('home') : (auth()->user()->hasRole('administrador') ? route('admin.dashboard') : route('dashboard')) }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/pol-bolivia/logo.png') }}" alt="" class="block h-9 w-auto">
                        <span class="text-lg font-bold text-blue-950">POL-Bolivia</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="{{ $esAdmin ? 'hidden xl:-my-px xl:ms-8 xl:flex xl:gap-x-5 2xl:gap-x-7' : 'hidden space-x-8 sm:-my-px sm:ms-10 sm:flex' }}">
                    @if (auth()->user()->hasRole('administrador'))
                        <x-nav-link class="whitespace-nowrap" :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                            Panel principal
                        </x-nav-link>

                        <x-nav-link class="whitespace-nowrap" :href="route('admin.usuarios.index')" :active="request()->routeIs('admin.usuarios.*')">
                            Usuarios
                        </x-nav-link>

                        <x-nav-link class="whitespace-nowrap" :href="route('admin.mascotas.index')" :active="request()->routeIs('admin.mascotas.*')">
                            Mascotas
                        </x-nav-link>

                        <x-nav-link class="whitespace-nowrap" :href="route('admin.solicitudes-adopcion.index')" :active="request()->routeIs('admin.solicitudes-adopcion.*')">
                            Solicitudes
                        </x-nav-link>
                        <x-nav-link class="whitespace-nowrap" :href="route('admin.seguimientos.index')" :active="request()->routeIs('admin.seguimientos.*')">
                            Seguimientos
                        </x-nav-link>
                        <x-nav-link class="whitespace-nowrap" :href="route('admin.postulaciones-voluntariado.index')" :active="request()->routeIs('admin.postulaciones-voluntariado.*')">
                            Postulaciones
                        </x-nav-link>
                        <x-nav-link class="whitespace-nowrap" :href="route('admin.voluntarios.index')" :active="request()->routeIs('admin.voluntarios.*')">
                            Voluntarios
                        </x-nav-link>
                        <x-nav-link class="whitespace-nowrap" :href="route('admin.reportes.index')" :active="request()->routeIs('admin.reportes.*')">
                            Reportes
                        </x-nav-link>
                        <x-nav-link class="whitespace-nowrap" :href="route('admin.auditoria.index')" :active="request()->routeIs('admin.auditoria.*')">
                            Auditoría
                        </x-nav-link>
                    @elseif (auth()->user()->hasRole('ciudadano'))
                        <x-nav-link :href="route('mascotas.catalogo')" :active="request()->routeIs('mascotas.catalogo', 'mascotas.public.show', 'solicitudes-adopcion.create')">
                            Mascotas
                        </x-nav-link>
                        <x-nav-link :href="route('solicitudes-adopcion.index')" :active="request()->routeIs('solicitudes-adopcion.*') && ! request()->routeIs('solicitudes-adopcion.create')">
                            Mis solicitudes
                        </x-nav-link>
                        <x-nav-link :href="route('postulaciones-voluntariado.index')" :active="request()->routeIs('postulaciones-voluntariado.*')">
                            Voluntariado
                        </x-nav-link>
                    @elseif (auth()->user()->hasRole('voluntario'))
                        <x-nav-link :href="route('seguimientos.index')" :active="request()->routeIs('seguimientos.*')">Mis seguimientos</x-nav-link>
                        <x-nav-link :href="route('postulaciones-voluntariado.index')" :active="request()->routeIs('postulaciones-voluntariado.index', 'postulaciones-voluntariado.show')">Mi postulación</x-nav-link>
                        <x-nav-link :href="route('solicitudes-adopcion.index')" :active="request()->routeIs('solicitudes-adopcion.index', 'solicitudes-adopcion.show')">Mis solicitudes</x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="{{ $esAdmin ? 'hidden xl:flex xl:items-center xl:ms-6' : 'hidden sm:flex sm:items-center sm:ms-6' }}">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div class="whitespace-nowrap">{{ Auth::user()->nombres }} {{ Auth::user()->apellidos }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Mi perfil
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    x-on:click.prevent="$el.closest('form').submit()">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center {{ $esAdmin ? 'xl:hidden' : 'sm:hidden' }}">
                <button @click="open = ! open" :aria-expanded="open ? 'true' : 'false'" aria-label="Abrir o cerrar el menú" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden {{ $esAdmin ? 'xl:hidden' : 'sm:hidden' }}">
        <div class="pt-2 pb-3 space-y-1">
            @if (auth()->user()->hasRole('administrador'))
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    Panel principal
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.usuarios.index')" :active="request()->routeIs('admin.usuarios.*')">
                    Usuarios
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.mascotas.index')" :active="request()->routeIs('admin.mascotas.*')">
                    Mascotas
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.solicitudes-adopcion.index')" :active="request()->routeIs('admin.solicitudes-adopcion.*')">
                    Solicitudes
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.seguimientos.index')" :active="request()->routeIs('admin.seguimientos.*')">Seguimientos</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.postulaciones-voluntariado.index')" :active="request()->routeIs('admin.postulaciones-voluntariado.*')">Postulaciones</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.voluntarios.index')" :active="request()->routeIs('admin.voluntarios.*')">Voluntarios</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reportes.index')" :active="request()->routeIs('admin.reportes.*')">Reportes</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.auditoria.index')" :active="request()->routeIs('admin.auditoria.*')">Auditoría</x-responsive-nav-link>
            @elseif (auth()->user()->hasRole('ciudadano'))
                <x-responsive-nav-link :href="route('mascotas.catalogo')" :active="request()->routeIs('mascotas.catalogo', 'mascotas.public.show', 'solicitudes-adopcion.create')">
                    Mascotas
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('solicitudes-adopcion.index')" :active="request()->routeIs('solicitudes-adopcion.*') && ! request()->routeIs('solicitudes-adopcion.create')">
                    Mis solicitudes
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('postulaciones-voluntariado.index')" :active="request()->routeIs('postulaciones-voluntariado.*')">Voluntariado</x-responsive-nav-link>
            @elseif (auth()->user()->hasRole('voluntario'))
                <x-responsive-nav-link :href="route('seguimientos.index')" :active="request()->routeIs('seguimientos.*')">Mis seguimientos</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('postulaciones-voluntariado.index')" :active="request()->routeIs('postulaciones-voluntariado.index', 'postulaciones-voluntariado.show')">Mi postulación</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('solicitudes-adopcion.index')" :active="request()->routeIs('solicitudes-adopcion.index', 'solicitudes-adopcion.show')">Mis solicitudes</x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->nombres }} {{ Auth::user()->apellidos }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Mi perfil
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            x-on:click.prevent="$el.closest('form').submit()">
                        Cerrar sesión
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
