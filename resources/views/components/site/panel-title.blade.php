{{-- El título de un bloque oscuro de la ficha de club: una marca amarilla
     inclinada y el texto en cursiva, el mismo idioma de Estadísticas. El texto
     se escribe tal cual se quiere ver (en mayúsculas si va en mayúsculas): los
     tests y los lectores de pantalla leen el HTML, no el CSS. --}}
<h2 {{ $attributes->merge(['class' => 'mb-3 flex items-center gap-2.5 font-display text-lg font-bold uppercase italic tracking-wide text-white']) }}>
    <span class="h-4 w-1.5 shrink-0 -skew-x-12 rounded-[1px] bg-brand" aria-hidden="true"></span>
    {{ $slot }}
</h2>
