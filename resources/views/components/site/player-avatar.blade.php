{{-- La foto la sube el administrador a mano; sin foto queda el círculo vacío. --}}
@props(['player'])

@if ($player->photo_path)
    <img src="{{ Storage::disk(config('filesystems.uploads'))->url($player->photo_path) }}" alt=""
         {{ $attributes->merge(['class' => 'size-8 shrink-0 rounded-full bg-surface-muted object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => 'size-8 shrink-0 rounded-full bg-surface-muted']) }}
          aria-hidden="true"></span>
@endif
