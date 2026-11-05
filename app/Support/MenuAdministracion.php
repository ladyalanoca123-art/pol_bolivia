<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Secciones del panel de administración: las usan el menú lateral (layouts/admin-navigation) y la ruta de
 * navegación de la barra superior (layouts/app), para que ambos digan siempre lo mismo.
 */
final class MenuAdministracion
{
    /**
     * Grupos del menú, en orden. Cada opción: [ruta, texto, rutas que la marcan como actual, icono (trazos de 24×24)].
     *
     * @return array<string, list<array{0: string, 1: string, 2: string, 3: string}>>
     */
    public static function grupos(): array
    {
        return [
            'Inicio' => [
                ['admin.dashboard', 'Panel principal', 'admin.dashboard', 'M3 10.5 12 3l9 7.5M5.25 9v10.5a.75.75 0 0 0 .75.75h4.5v-6h3v6H18a.75.75 0 0 0 .75-.75V9'],
            ],
            'Adopciones' => [
                ['admin.solicitudes-adopcion.index', 'Solicitudes', 'admin.solicitudes-adopcion.*', 'M9 12h6M9 16h6M8.25 3.75h7.5l3.75 3.75v12a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 1-.75-.75V4.5a.75.75 0 0 1 .75-.75h3Z'],
                ['admin.mascotas.index', 'Mascotas', 'admin.mascotas.*', 'M12 20.25s-7.5-4.2-7.5-10.1A4.15 4.15 0 0 1 12 7.6a4.15 4.15 0 0 1 7.5 2.55c0 5.9-7.5 10.1-7.5 10.1Z'],
                ['admin.seguimientos.index', 'Seguimientos', 'admin.seguimientos.*', 'M8.25 3v2.25M15.75 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5ZM9 14.25l2.25 2.25L15 12.75'],
            ],
            'Voluntariado' => [
                ['admin.postulaciones-voluntariado.index', 'Postulaciones', 'admin.postulaciones-voluntariado.*', 'M9 4.5H7.5A1.5 1.5 0 0 0 6 6v13.5A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H15M9 4.5A1.5 1.5 0 0 0 10.5 6h3A1.5 1.5 0 0 0 15 4.5M9 4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5M9.75 13.5l1.5 1.5 3-3'],
                ['admin.voluntarios.index', 'Voluntarios', 'admin.voluntarios.*', 'M15 19.5v-1.5a3.75 3.75 0 0 0-3.75-3.75h-4.5A3.75 3.75 0 0 0 3 18v1.5M16.5 10.5a3 3 0 0 0 0-6M21 19.5V18a3.75 3.75 0 0 0-2.25-3.44M12.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z'],
            ],
            'Administración' => [
                ['admin.usuarios.index', 'Usuarios', 'admin.usuarios.*', 'M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0'],
                ['admin.reportes.index', 'Reportes', 'admin.reportes.*', 'M3.75 3.75v15.75h16.5M8.25 15v-3.75M12.75 15V8.25M17.25 15v-5.25'],
                ['admin.auditoria.index', 'Auditoría', 'admin.auditoria.*', 'M12 3 4.5 6v5.25c0 4.6 3.2 8.15 7.5 9.75 4.3-1.6 7.5-5.15 7.5-9.75V6L12 3Zm-3 9 2.25 2.25L15.75 9.75'],
            ],
        ];
    }

    /**
     * Ruta de navegación de la página actual: ['POL-Bolivia', grupo, sección]. En el panel principal y en las páginas
     * que no son del menú (p. ej. «Mi perfil») muestra solo «POL-Bolivia › Administración» o el nombre de la página.
     *
     * @return list<string>
     */
    public static function migaDePan(Request $request): array
    {
        if ($request->routeIs('profile.*')) {
            return ['POL-Bolivia', 'Mi perfil'];
        }

        // Las incidencias se atienden desde el seguimiento de cada adopción.
        if ($request->routeIs('admin.incidencias.*')) {
            return ['POL-Bolivia', 'Adopciones', 'Seguimientos'];
        }

        foreach (self::grupos() as $grupo => $opciones) {
            foreach ($opciones as [, $texto, $patron]) {
                if ($request->routeIs($patron)) {
                    return $grupo === 'Inicio' ? ['POL-Bolivia', 'Administración'] : ['POL-Bolivia', $grupo, $texto];
                }
            }
        }

        return ['POL-Bolivia', 'Administración'];
    }
}
