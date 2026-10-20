{{-- La marca de la liga: una placa amarilla inclinada y el nombre en cursiva,
     como los títulos del sitio. Vive en UN sitio a propósito: la liga se
     renombrará (decisión del propietario, aplazada), y así el cambio es aquí.

     `size` es la altura de letra: la cabecera la usa más grande que el pie. --}}
@props(['size' => 'text-[22px] lg:text-[26px]'])

<span {{ $attributes->merge(['class' => 'flex items-center gap-2.5 font-display leading-none '.$size]) }}>
    <span class="h-[0.8em] w-[0.38em] shrink-0 -skew-x-12 rounded-[2px] bg-brand" aria-hidden="true"></span>
    <span class="-skew-x-6 italic">
        <span class="font-extrabold text-brand">AMAZON</span>
        <span class="font-bold tracking-[0.1em] text-white">TOERNOOIEN</span>
    </span>
</span>
