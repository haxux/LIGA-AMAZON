{{-- La foto la sube el administrador a mano; sin foto queda el círculo vacío.

     El tamaño es una propiedad y no una clase suelta por el atributo, como en
     team-crest: fusionar `class` dejaría dos `size-` peleándose y ganaría el que
     saliera primero en la hoja, que no es decisión de quien lo escribe. --}}
@props(['player', 'size' => 'size-8'])

@if ($player->photo_path)
    <img src="{{ Storage::disk(config('filesystems.uploads'))->url($player->photo_path) }}" alt=""
         {{ $attributes->merge(['class' => $size.' shrink-0 rounded-full bg-surface-muted object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => $size.' shrink-0 rounded-full bg-surface-muted']) }}
          aria-hidden="true"></span>
@endif
