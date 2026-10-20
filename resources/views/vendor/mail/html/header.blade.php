@props(['url'])
{{-- Encabezado de los correos de POL-Bolivia: logo (requiere APP_URL pública para verse en el correo). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/pol-bolivia/logos/POL_02_horizontal_web.png') }}" class="logo" alt="POL-Bolivia">
</a>
</td>
</tr>
