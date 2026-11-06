<?php

namespace App\Support;

/**
 * Íconos de línea fina (trazo de 24 × 24) de las barras de filtro rápido del panel (componente x-admin.pestanas).
 * Se usan como atributo «d» de un <path> con stroke.
 */
class IconosPestanas
{
    private const CALENDARIO = 'M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z';

    public const RESUMEN = 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z';

    public const MASCOTAS = 'M12 13.5c-2.4 0-4.8 2.5-4.8 4.6 0 1.4 1.1 2.1 2.4 2.1.9 0 1.5-.5 2.4-.5s1.5.5 2.4.5c1.3 0 2.4-.7 2.4-2.1 0-2.1-2.4-4.6-4.8-4.6ZM4.6 11a1.7 2.1 0 1 0 3.4 0 1.7 2.1 0 1 0-3.4 0ZM16 11a1.7 2.1 0 1 0 3.4 0 1.7 2.1 0 1 0-3.4 0ZM7.8 6.6a1.7 2.1 0 1 0 3.4 0 1.7 2.1 0 1 0-3.4 0ZM12.8 6.6a1.7 2.1 0 1 0 3.4 0 1.7 2.1 0 1 0-3.4 0Z';

    public const SOLICITUDES = 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Zm0 0v5h5M9 13h6M9 17h6';

    public const SEGUIMIENTOS = self::CALENDARIO.'M9 15l2 2 4-4';

    public const EQUIPO = 'M17 20a5 5 0 0 0-10 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM21 19a4 4 0 0 0-4-4M17 10a2.5 2.5 0 1 0 0-5M3 19a4 4 0 0 1 4-4M7 10a2.5 2.5 0 1 1 0-5';

    public const CORAZON = 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z';

    public const PERSONA = 'M15.5 7.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM5 20a7 7 0 0 1 14 0';

    public const PERSONA_TACHADA = 'M13.5 8a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM3.5 20a6.5 6.5 0 0 1 10.6-5M16 15l5 5m0-5-5 5';

    public const ESCUDO = 'M12 3 4 6v6c0 4.5 3.4 8 8 9 4.6-1 8-4.5 8-9V6l-8-3Zm-3 9 2 2 4-4';

    public const LISTA = 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01';

    public const BANDEJA = 'M4 13h4l1.5 3h5l1.5-3h4M4 13l2-8h12l2 8v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Z';

    public const LUPA = 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm9 2-4-4';

    public const MARCADOR = 'M7 4h10a1 1 0 0 1 1 1v15l-6-4-6 4V5a1 1 0 0 1 1-1Z';

    public const ARCHIVO = 'M4 4h16v3H4zM5 7l1 12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1l1-12M10 11h4';

    public const CHECK = 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-4-9 3 3 5-6';

    public const HOY = self::CALENDARIO.'M8.5 13h3v3h-3z';

    public const MES_ANTERIOR = self::CALENDARIO.'M10 15h4';

    public const VARIOS_MESES = self::CALENDARIO.'M9 17v-2m3 2v-4m3 4v-3';

    public const ANIO = self::CALENDARIO.'M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01M16 17h.01';

    public const AJUSTES = 'M4 6h9m4 0h3M4 12h3m4 0h9M4 18h11m4 0h1M15 4v4M9 10v4M17 16v4';
}
