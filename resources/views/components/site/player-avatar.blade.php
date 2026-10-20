{{-- La foto la sube el administrador a mano; sin foto queda el círculo vacío.

     El tamaño es una propiedad y no una clase suelta por el atributo, como en
     team-crest: fusionar `class` dejaría dos `size-` peleándose y ganaría el que
     saliera primero en la hoja, que no es decisión de quien lo escribe.

     Con `initials`, el círculo sin foto lleva las iniciales: en una lista larga
     de caras vacías es lo único que distingue una fila de otra a simple vista. --}}
@props(['player', 'size' => 'size-8', 'initials' => false])

@if ($player->photo_path)
    <img src="{{ Storage::disk(config('filesystems.uploads'))->url($player->photo_path) }}" alt=""
         {{ $attributes->merge(['class' => $size.' shrink-0 rounded-full bg-surface-muted object-cover']) }}>
@elseif ($initials)
    <span {{ $attributes->merge(['class' => $size.' grid shrink-0 place-items-center rounded-full bg-surface-muted font-display font-semibold text-white/55']) }}
          aria-hidden="true">{{ collect(preg_split('/\s+/u', trim($player->name)))->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('') }}</span>
@else
    <span {{ $attributes->merge(['class' => $size.' shrink-0 rounded-full bg-surface-muted']) }}
          aria-hidden="true"></span>
@endif
