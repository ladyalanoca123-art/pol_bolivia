<?php

namespace App\Http\Requests;

use App\Models\Mascota;
use App\Models\SolicitudAdopcion;
use App\Rules\TelefonoBoliviano;
use App\Support\CuestionarioAdopcion as C;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el cuestionario de adopción A2 (26 preguntas).
 * Las preguntas condicionales que no aplican se excluyen: no se validan ni se guardan,
 * aunque el navegador las envíe.
 */
class StoreSolicitudAdopcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Mismo formato que el registro y «Mi perfil»: acepta +591, espacios y guiones. */
    protected function prepareForValidation(): void
    {
        $this->merge(['telefono_contacto' => TelefonoBoliviano::normalizar($this->input('telefono_contacto'))]);
    }

    public function rules(): array
    {
        $viviendaPropia = fn () => $this->input('tenencia') === 'propia';
        $sinExterior = fn () => ! in_array($this->input('permanencia'), C::PERMANENCIA_CON_EXTERIOR, true);
        $sinNinos = fn () => (int) $this->input('ninos') === 0;
        $sinMascotas = fn () => $this->input('otras_mascotas') !== 'si';
        $sinDetalleAnteriores = fn () => ! in_array($this->input('mascotas_anteriores'), C::ANTERIORES_CON_DETALLE, true);
        $texto = ['string', 'not_regex:/^\s*$/'];

        return [
            // Sección 1 · Vivienda
            'tipo_vivienda' => ['required', Rule::in(C::claves('tipo_vivienda'))],
            'tenencia' => ['required', Rule::in(C::claves('tenencia'))],
            'permiso_propietario' => [Rule::excludeIf($viviendaPropia), 'required', Rule::in(C::claves('permiso_propietario'))],
            'permanencia' => ['required', Rule::in(C::claves('permanencia'))],
            'cerramiento' => [Rule::excludeIf($sinExterior), 'required', Rule::in(C::claves('cerramiento'))],

            // Sección 2 · Hogar
            'adultos' => ['required', 'integer', 'min:1', 'max:20'],
            'ninos' => ['required', 'integer', 'min:0', 'max:20'],
            'edades_ninos' => [Rule::excludeIf($sinNinos), 'required', ...$texto, 'max:100'],
            'acuerdo_hogar' => ['required', Rule::in(C::claves('acuerdo_hogar'))],
            'otras_mascotas' => ['required', Rule::in(C::claves('otras_mascotas'))],
            'mascotas_actuales' => [Rule::excludeIf($sinMascotas), 'required', 'array:cantidad,especies,vacunas,esterilizadas'],
            'mascotas_actuales.cantidad' => [Rule::excludeIf($sinMascotas), 'required', 'integer', 'min:1', 'max:30'],
            'mascotas_actuales.especies' => [Rule::excludeIf($sinMascotas), 'required', 'array', 'min:1'],
            'mascotas_actuales.especies.*' => [Rule::excludeIf($sinMascotas), 'distinct', Rule::in(C::claves('especies'))],
            'mascotas_actuales.vacunas' => [Rule::excludeIf($sinMascotas), 'required', Rule::in(C::claves('estado_grupo'))],
            'mascotas_actuales.esterilizadas' => [Rule::excludeIf($sinMascotas), 'required', Rule::in(C::claves('estado_grupo'))],

            // Sección 3 · Experiencia
            'mascotas_anteriores' => ['required', Rule::in(C::claves('mascotas_anteriores'))],
            'que_ocurrio' => [Rule::excludeIf($sinDetalleAnteriores), 'required', ...$texto, 'max:500'],

            // Sección 4 · Rutina y cuidados
            'responsable' => ['required', Rule::in(C::claves('responsable'))],
            'responsable_otro' => ['exclude_unless:responsable,otra_persona', 'required', ...$texto, 'max:100'],
            'apoyo' => ['required', Rule::in(C::claves('apoyo'))],
            'tiempo_sola' => ['required', Rule::in(C::claves('tiempo_sola'))],

            // Sección 5 · Compromiso
            'motivo' => ['required', Rule::in(C::claves('motivo'))],
            'motivo_otro' => ['exclude_unless:motivo,otro', 'nullable', 'string', 'max:150'],
            'compromiso_vacunas' => ['required', Rule::in(C::claves('compromiso_vacunas'))],
            'si_no_puede' => ['required', Rule::in(C::claves('si_no_puede'))],
            'si_no_puede_otro' => ['exclude_unless:si_no_puede,otro', 'nullable', 'string', 'max:150'],

            // Sección 6 · Visita y consentimientos
            // Obligatorio para solicitar: la administración y el voluntario del seguimiento contactan por WhatsApp.
            'telefono_contacto' => ['bail', 'required', 'string', 'max:20', new TelefonoBoliviano],
            'zona_aproximada' => ['required', ...$texto, 'max:100'],
            'horarios_visita' => ['required', 'array', 'min:1'],
            'horarios_visita.*' => ['distinct', Rule::in(C::claves('horarios_visita'))],
            'acepta_visita_previa' => ['accepted'],
            'acepta_seguimiento' => ['accepted'],
            'no_ceder' => ['accepted'],
            'declara_informacion_veraz' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_vivienda.required' => 'Selecciona el tipo de vivienda.',
            'tenencia.required' => 'Indica de quién es la vivienda.',
            'permiso_propietario.required' => 'Indica si el propietario permite tener mascotas.',
            'permanencia.required' => 'Indica dónde permanecerá la mascota.',
            'cerramiento.required' => 'Indica si el espacio exterior tiene un cerramiento seguro.',
            'adultos.required' => 'Escribe cuántos adultos viven en el hogar.',
            'adultos.min' => 'Debe vivir al menos 1 adulto en el hogar (incluyéndote).',
            'ninos.required' => 'Escribe cuántos niños viven en el hogar (puede ser 0).',
            'edades_ninos.required' => 'Escribe las edades de los niños.',
            'acuerdo_hogar.required' => 'Indica si todos en el hogar están de acuerdo.',
            'otras_mascotas.required' => 'Indica si tienes otras mascotas.',
            'mascotas_actuales.cantidad.required' => 'Escribe cuántas mascotas tienes.',
            'mascotas_actuales.especies.required' => 'Marca al menos una especie.',
            'mascotas_actuales.vacunas.required' => 'Indica si sus vacunas están al día.',
            'mascotas_actuales.esterilizadas.required' => 'Indica si están esterilizadas.',
            'mascotas_anteriores.required' => 'Selecciona qué ocurrió con tus mascotas anteriores.',
            'que_ocurrio.required' => 'Cuéntanos brevemente qué ocurrió.',
            'responsable.required' => 'Indica quién será el responsable principal.',
            'responsable_otro.required' => 'Indica quién será el responsable.',
            'apoyo.required' => 'Indica si habrá otra persona que pueda atender a la mascota.',
            'tiempo_sola.required' => 'Selecciona cuánto tiempo estaría sola la mascota.',
            'motivo.required' => 'Selecciona el motivo principal de la adopción.',
            'compromiso_vacunas.required' => 'Responde si te comprometes con las vacunas y la desparasitación.',
            'si_no_puede.required' => 'Selecciona qué harías si ya no pudieras cuidarla.',
            'telefono_contacto.required' => 'Escribe un teléfono con WhatsApp para que la fundación pueda coordinar contigo.',
            'telefono_contacto.string' => TelefonoBoliviano::MENSAJE,
            'telefono_contacto.max' => TelefonoBoliviano::MENSAJE,
            'zona_aproximada.required' => 'Escribe tu zona aproximada.',
            'horarios_visita.required' => 'Marca al menos un horario para la visita.',
            'acepta_visita_previa.accepted' => 'Debes aceptar la visita previa al hogar.',
            'acepta_seguimiento.accepted' => 'Debes aceptar el seguimiento posterior a la adopción.',
            'no_ceder.accepted' => 'Debes aceptar el compromiso de no vender ni ceder la mascota.',
            'declara_informacion_veraz.accepted' => 'Debes aceptar la declaración de veracidad.',
            '*.in' => 'Selecciona una opción válida.',
            '*.*.in' => 'Selecciona una opción válida.',
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $mascota = $this->route('mascota');

            if (! $mascota instanceof Mascota || $mascota->estado !== 'disponible') {
                $validator->errors()->add('mascota', 'La mascota ya no está disponible para adopción.');

                return;
            }

            $activas = $this->user()?->solicitudesAdopcion()->whereIn('estado', SolicitudAdopcion::ESTADOS_ACTIVOS);

            if ((clone $activas)->where('mascota_id', $mascota->id)->exists()) {
                $validator->errors()->add('mascota', 'Ya tienes una solicitud activa para esta mascota.');
            } elseif ($activas->count() >= SolicitudAdopcion::MAX_ACTIVAS) {
                // PostgreSQL lo exige también (validar_limite_solicitudes_activas), con bloqueo ante envíos simultáneos.
                $validator->errors()->add('mascota', SolicitudAdopcion::LIMITE_ALCANZADO);
            }
        }];
    }
}
