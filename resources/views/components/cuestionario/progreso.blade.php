{{--
    Indicador de progreso compartido por los cuestionarios (adopción y voluntariado).
    Cuenta preguntas numeradas, no campos: cada contenedor con data-num="N" es parte de la pregunta N.
    - Una pregunta oculta (condicional que no aplica) cuenta como "no corresponde a tu caso".
    - Una pregunta cuyo contenedor tiene data-opcional cuenta como "opcional" mientras esté vacía.
    Uso: <x-cuestionario.progreso /> antes del <form>, y x-data="cuestionarioFormulario({...})" en el form.
--}}
<script nonce="{{ Vite::cspNonce() }}">
    window.cuestionarioFormulario = (inicial) => ({
        ...inicial,
        texto: '',
        porcentaje: 0,
        init() { this.$nextTick(() => this.progreso()); },
        progreso() {
            const grupos = {};
            this.$root.querySelectorAll('[data-num]').forEach((el) => {
                (grupos[el.dataset.num] = grupos[el.dataset.num] || []).push(el);
            });
            const lleno = (c) => (c.type === 'radio' || c.type === 'checkbox') ? c.checked : c.value.trim() !== '';
            let respondidas = 0, noAplican = 0, opcionales = 0, opcionalesLlenas = 0;
            const total = Object.keys(grupos).length;
            for (const els of Object.values(grupos)) {
                const visibles = els.filter((el) => el.offsetParent !== null);
                if (!visibles.length) { noAplican++; continue; }
                if (visibles.every((el) => el.dataset.opcional !== undefined)) {
                    const campos = visibles.flatMap((el) => [...el.querySelectorAll('input, textarea')]);
                    if (campos.some(lleno)) { respondidas++; opcionalesLlenas++; } else { opcionales++; }
                    continue;
                }
                const completa = visibles.every((el) => {
                    const campos = [...el.querySelectorAll('input, textarea')].filter((c) => !c.dataset.opcional);
                    const marcables = campos.filter((c) => c.type === 'radio' || c.type === 'checkbox');
                    const textos = campos.filter((c) => c.type !== 'radio' && c.type !== 'checkbox');
                    return (!marcables.length || marcables.some((c) => c.checked)) && textos.every((c) => c.value.trim() !== '');
                });
                if (completa) respondidas++;
            }
            const pendientes = total - respondidas - noAplican - opcionales;
            const obligatorias = total - noAplican - opcionales - opcionalesLlenas;
            const pl = (n, uno, varios) => n + ' ' + (n === 1 ? uno : varios);
            this.porcentaje = obligatorias ? Math.round((respondidas - opcionalesLlenas) / obligatorias * 100) : 0;
            this.texto = `${total} preguntas: ${pl(respondidas, 'respondida', 'respondidas')} · ${pl(pendientes, 'pendiente', 'pendientes')}`
                + (opcionales ? ` · ${pl(opcionales, 'opcional', 'opcionales')}` : '')
                + ` · ${pl(noAplican, 'no corresponde a tu caso', 'no corresponden a tu caso')}`;
        },
    });
</script>
