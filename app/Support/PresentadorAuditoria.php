<?php

namespace App\Support;

use App\Models\Auditoria;
use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use Illuminate\Support\Str;

/**
 * Presenta un registro de auditoría en lenguaje claro para la pantalla «Auditoría»: módulo, frase de la acción,
 * enlace al registro y cambios campo por campo con nombres y valores legibles (sin JSON).
 */
class PresentadorAuditoria
{
    /** modelo => [nombre del módulo, «un/una …» para la frase, ruta del detalle o null, color]. */
    public const MODULOS = [
        'SolicitudAdopcion' => ['Solicitudes de adopción', 'una solicitud de adopción', 'admin.solicitudes-adopcion.show', 'amber'],
        'Mascota' => ['Mascotas', 'una mascota', 'admin.mascotas.show', 'emerald'],
        'FotoMascota' => ['Fotos de mascotas', 'una foto de mascota', null, 'emerald'],
        'SeguimientoAdopcion' => ['Seguimientos', 'un seguimiento', 'admin.seguimientos.show', 'sky'],
        'VisitaSeguimiento' => ['Visitas de seguimiento', 'una visita de seguimiento', null, 'sky'],
        'Incidencia' => ['Incidencias', 'una incidencia', null, 'red'],
        'PostulacionVoluntariado' => ['Postulaciones', 'una postulación de voluntariado', 'admin.postulaciones-voluntariado.show', 'violet'],
        'Voluntario' => ['Voluntarios', 'un voluntario', 'admin.voluntarios.show', 'violet'],
        'User' => ['Usuarios', 'una cuenta de usuario', 'admin.usuarios.show', 'slate'],
    ];

    public const ACCIONES = [
        'creado' => ['Creación', 'registró'],
        'actualizado' => ['Cambio', 'actualizó'],
        'eliminado' => ['Eliminación', 'eliminó'],
    ];

    /** Nombres de los campos que se repiten en varios módulos. */
    private const CAMPOS = [
        'estado' => 'Estado', 'nombre' => 'Nombre', 'nombres' => 'Nombres', 'apellidos' => 'Apellidos',
        'email' => 'Correo', 'telefono' => 'Teléfono', 'rol' => 'Rol', 'es_principal' => 'Principal',
        'email_verified_at' => 'Correo verificado', 'comentarios_admin' => 'Comentario del equipo',
        'motivo_no_concretada' => 'Motivo de la entrega no concretada', 'motivo_cancelacion' => 'Motivo de cancelación',
        'numero_acta' => 'Número de acta', 'aprobada_at' => 'Fecha de aprobación', 'entregada_at' => 'Fecha de entrega',
        'compromiso_firmado' => 'Compromiso firmado', 'compromiso_foto_path' => 'Foto del acta firmada',
        'resuelta_at' => 'Fecha de resolución', 'nivel_alerta' => 'Revisión', 'alertas' => 'Alertas',
        'respuestas' => 'Respuestas del cuestionario', 'version_cuestionario' => 'Versión del cuestionario',
        'version_reglas' => 'Versión de las reglas', 'zona_aproximada' => 'Zona', 'horario_preferido' => 'Horario preferido',
        'solicitante_id' => 'Solicitante', 'mascota_id' => 'Mascota', 'voluntario_id' => 'Voluntario', 'usuario_id' => 'Usuario',
        'solicitud_id' => 'Solicitud', 'seguimiento_id' => 'Seguimiento', 'visita_id' => 'Visita', 'registrado_por' => 'Registrada por',
        'reportado_por' => 'Reportada por', 'atendida_por' => 'Atendida por', 'revisado_por' => 'Revisada por',
        'especie' => 'Especie', 'raza' => 'Raza', 'edad' => 'Edad', 'edad_en' => 'Edad en', 'sexo' => 'Sexo', 'color' => 'Color',
        'tamano' => 'Tamaño', 'descripcion' => 'Descripción', 'url' => 'Archivo', 'tipo' => 'Tipo', 'orden' => 'Orden',
        'fecha_programada' => 'Fecha programada', 'fecha_realizada' => 'Fecha realizada', 'observaciones' => 'Observaciones',
        'estado_salud' => 'Estado de salud', 'foto_url' => 'Foto', 'comentarios_resolucion' => 'Resolución',
        'atendida_at' => 'Fecha de atención', 'fecha_inicio' => 'Inicio', 'fecha_cierre' => 'Cierre',
        'observaciones_cierre' => 'Observaciones de cierre', 'revisado_at' => 'Fecha de revisión',
        'tiene_otras_mascotas' => 'Tiene otras mascotas', 'viven_otras_personas' => 'Viven otras personas',
        'declara_informacion_veraz' => 'Declara información veraz', 'habilidades' => 'Habilidades',
    ];

    /** Valores legibles de los campos con opciones fijas (además del estado). */
    private const OPCIONES = [
        'tipo' => ['salud' => 'Salud', 'maltrato' => 'Posible maltrato', 'perdida_contacto' => 'Pérdida de contacto', 'otro' => 'Otro', 'foto' => 'Foto', 'video' => 'Video'],
        'especie' => ['perro' => 'Perro', 'gato' => 'Gato'],
        'sexo' => ['macho' => 'Macho', 'hembra' => 'Hembra'],
        'tamano' => ['pequeno' => 'Pequeño', 'mediano' => 'Mediano', 'grande' => 'Grande'],
        'edad_en' => ['anios' => 'Años', 'años' => 'Años', 'meses' => 'Meses'],
        'rol' => ['administrador' => 'Administrador', 'voluntario' => 'Voluntario', 'ciudadano' => 'Ciudadano'],
        'nivel_alerta' => ['verde' => 'Sin observaciones', 'amarillo' => 'Requiere revisión', 'rojo' => 'Condición incompatible'],
    ];

    /** Estados legibles por módulo. */
    private static function estados(string $modelo): array
    {
        return match ($modelo) {
            'SolicitudAdopcion' => SolicitudAdopcion::ESTADOS_LEGIBLES,
            'Mascota' => Mascota::ESTADOS_LEGIBLES,
            'SeguimientoAdopcion' => ['sin_asignar' => 'Sin asignar', 'activo' => 'Activo', 'cerrado' => 'Cerrado'],
            'VisitaSeguimiento' => ['programada' => 'Programada', 'realizada' => 'Realizada', 'no_asistio' => 'No asistió'],
            'Incidencia' => ['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'atendida' => 'Atendida'],
            'PostulacionVoluntariado' => ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'cancelada' => 'Cancelada'],
            'Voluntario' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
            'User' => [true => 'Activa', false => 'Desactivada', 1 => 'Activa', 0 => 'Desactivada'],
            default => [],
        };
    }

    public static function modulo(string $modelo): string
    {
        return self::MODULOS[$modelo][0] ?? Str::headline($modelo);
    }

    public static function color(string $modelo): string
    {
        return self::MODULOS[$modelo][3] ?? 'slate';
    }

    /** «Lady Alanoca actualizó una solicitud de adopción», con «El sistema» si no hubo usuario. */
    public static function frase(Auditoria $auditoria): string
    {
        return self::actor($auditoria).' '.self::accion($auditoria);
    }

    /** «actualizó una solicitud de adopción». */
    public static function accion(Auditoria $auditoria): string
    {
        $verbo = self::ACCIONES[$auditoria->accion][1] ?? $auditoria->accion;
        $objeto = self::MODULOS[$auditoria->modelo][1] ?? 'un registro de '.Str::headline($auditoria->modelo);

        return $verbo.' '.$objeto;
    }

    public static function actor(Auditoria $auditoria): string
    {
        return match (true) {
            $auditoria->usuario_id === null => 'El sistema',
            $auditoria->usuario !== null => trim($auditoria->usuario->nombres.' '.$auditoria->usuario->apellidos),
            default => 'Un usuario eliminado',
        };
    }

    /** Enlace al registro, si el módulo tiene pantalla de detalle y el registro no fue eliminado. */
    public static function enlace(Auditoria $auditoria): ?string
    {
        $ruta = self::MODULOS[$auditoria->modelo][2] ?? null;

        return $ruta && $auditoria->accion !== 'eliminado' && $auditoria->modelo_id ? route($ruta, $auditoria->modelo_id) : null;
    }

    /**
     * Cambios campo por campo: [campo legible, antes, después]. En una creación o eliminación solo hay un valor.
     *
     * @return list<array{0: string, 1: ?string, 2: ?string}>
     */
    public static function cambios(Auditoria $auditoria): array
    {
        $nuevos = $auditoria->valores_nuevos ?? [];
        $anteriores = $auditoria->valores_anteriores ?? [];
        $campos = $auditoria->accion === 'eliminado' ? array_keys($anteriores) : array_keys($nuevos);
        $filas = [];

        foreach ($campos as $campo) {
            if (in_array($campo, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $antes = array_key_exists($campo, $anteriores) ? self::valor($auditoria->modelo, $campo, $anteriores[$campo]) : null;
            $despues = $auditoria->accion === 'eliminado' ? null : self::valor($auditoria->modelo, $campo, $nuevos[$campo] ?? null);

            // En una creación no se listan los campos vacíos.
            if ($auditoria->accion === 'creado' && $despues === '—') {
                continue;
            }

            $filas[] = [self::campo($campo), $auditoria->accion === 'actualizado' ? $antes : ($auditoria->accion === 'eliminado' ? $antes : null), $despues];
        }

        return $filas;
    }

    /** Estado anterior y nuevo, si el registro cambió de estado (para destacarlo en la lista). */
    public static function cambioDeEstado(Auditoria $auditoria): ?array
    {
        $nuevos = $auditoria->valores_nuevos ?? [];

        if ($auditoria->accion !== 'actualizado' || ! array_key_exists('estado', $nuevos)) {
            return null;
        }

        return [
            self::valor($auditoria->modelo, 'estado', $auditoria->valores_anteriores['estado'] ?? null),
            self::valor($auditoria->modelo, 'estado', $nuevos['estado']),
        ];
    }

    public static function campo(string $campo): string
    {
        return self::CAMPOS[$campo] ?? Str::ucfirst(str_replace('_', ' ', $campo));
    }

    public static function valor(string $modelo, string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return '—';
        }

        if ($campo === 'estado' && (is_string($valor) || is_bool($valor) || is_int($valor))) {
            return self::estados($modelo)[$valor] ?? Str::ucfirst(str_replace('_', ' ', (string) $valor));
        }

        if (is_string($valor) && isset(self::OPCIONES[$campo][$valor])) {
            return self::OPCIONES[$campo][$valor];
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_array($valor)) {
            return $campo === 'alertas' ? count($valor).' '.(count($valor) === 1 ? 'alerta' : 'alertas') : 'Datos del formulario';
        }

        $texto = (string) $valor;

        // Fechas guardadas como texto ISO o «AAAA-MM-DD hh:mm:ss».
        if (preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}(:?\d{2})?)?)?$/', $texto)) {
            try {
                $fecha = \Illuminate\Support\Carbon::parse($texto)->timezone(config('app.timezone'));

                return strlen($texto) === 10 ? $fecha->format('d/m/Y') : $fecha->format('d/m/Y H:i');
            } catch (\Throwable) {
                return $texto;
            }
        }

        return Str::limit($texto, 140);
    }
}
