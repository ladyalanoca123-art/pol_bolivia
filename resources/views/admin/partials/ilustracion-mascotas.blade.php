{{--
    Ilustración decorativa de un perro y un gato (saludo del panel principal). Dibujada en SVG con los colores de POL:
    no depende de imágenes externas y se ve nítida en cualquier pantalla. $clase: clases del <svg>.
--}}
<svg viewBox="0 0 240 150" class="{{ $clase ?? '' }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    {{-- Fondo --}}
    <ellipse cx="124" cy="138" rx="104" ry="10" fill="#dbeafe" />
    <circle cx="200" cy="38" r="26" fill="#e0f2fe" />
    {{-- Destellos --}}
    <g stroke="#f59e0b" stroke-width="4" stroke-linecap="round">
        <path d="M30 52 l12 4" /><path d="M40 30 l8 10" /><path d="M62 18 l2 12" />
        <path d="M190 18 l-4 10" /><path d="M214 30 l-10 6" />
    </g>

    {{-- Perro --}}
    <g>
        <path d="M66 140 C62 112 72 96 98 94 C124 96 134 112 130 140 Z" fill="#f8fafc" stroke="#cbd5e1" stroke-width="1.5" />
        <path d="M78 104 Q98 116 118 104" fill="none" stroke="#f59e0b" stroke-width="7" stroke-linecap="round" />
        <circle cx="98" cy="113" r="5" fill="#fbbf24" stroke="#d97706" stroke-width="1.5" />
        <ellipse cx="62" cy="64" rx="13" ry="25" transform="rotate(22 62 64)" fill="#92400e" />
        <ellipse cx="134" cy="64" rx="13" ry="25" transform="rotate(-22 134 64)" fill="#92400e" />
        <ellipse cx="98" cy="64" rx="36" ry="34" fill="#f59e0b" />
        <path d="M98 30 C88 44 86 60 92 74 L104 74 C110 60 108 44 98 30 Z" fill="#fff7ed" />
        <ellipse cx="98" cy="80" rx="20" ry="15" fill="#fff7ed" />
        <circle cx="84" cy="62" r="4.5" fill="#172554" /><circle cx="85.5" cy="60.5" r="1.4" fill="#fff" />
        <circle cx="112" cy="62" r="4.5" fill="#172554" /><circle cx="113.5" cy="60.5" r="1.4" fill="#fff" />
        <ellipse cx="98" cy="74" rx="6" ry="4.5" fill="#172554" />
        <path d="M91 82 Q98 88 105 82" fill="none" stroke="#172554" stroke-width="2.2" stroke-linecap="round" />
        <path d="M95 86 Q98 94 101 86 Z" fill="#f87171" />
    </g>

    {{-- Gato --}}
    <g>
        <path d="M140 140 C138 118 146 106 166 104 C186 106 194 118 192 140 Z" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="1.5" />
        <path d="M188 128 C206 122 206 104 196 98" fill="none" stroke="#94a3b8" stroke-width="7" stroke-linecap="round" />
        <path d="M140 66 L144 38 L160 56 Z" fill="#94a3b8" /><path d="M146 60 L147 46 L155 55 Z" fill="#fecdd3" />
        <path d="M192 66 L188 38 L172 56 Z" fill="#94a3b8" /><path d="M186 60 L185 46 L177 55 Z" fill="#fecdd3" />
        <ellipse cx="166" cy="78" rx="29" ry="26" fill="#94a3b8" />
        <ellipse cx="166" cy="88" rx="15" ry="11" fill="#f8fafc" />
        <path d="M160 58 Q166 64 172 58" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" />
        <ellipse cx="155" cy="76" rx="4" ry="4.8" fill="#172554" /><circle cx="156.3" cy="74.6" r="1.2" fill="#fff" />
        <ellipse cx="177" cy="76" rx="4" ry="4.8" fill="#172554" /><circle cx="178.3" cy="74.6" r="1.2" fill="#fff" />
        <path d="M163 85 L169 85 L166 88.5 Z" fill="#fb7185" />
        <path d="M160 91 Q166 95 172 91" fill="none" stroke="#475569" stroke-width="1.8" stroke-linecap="round" />
        <g stroke="#64748b" stroke-width="1.4" stroke-linecap="round">
            <path d="M150 86 l-14 -2" /><path d="M150 90 l-13 3" /><path d="M182 86 l14 -2" /><path d="M182 90 l13 3" />
        </g>
    </g>
</svg>
