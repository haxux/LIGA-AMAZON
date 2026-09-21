@props(['user', 'size' => 'size-10', 'text' => 'text-sm'])

@php
    use Illuminate\Support\Str;

    // El escudo de su club es el mejor retrato que hay de un técnico: sin fotos
    // de personas (decisión del bloque), es lo que de verdad le identifica.
    $club = $user?->isCoach() ? $user->club : null;

    $initials = Str::of($user?->name ?? '?')
        ->squish()
        ->explode(' ')
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    // Color estable por nombre: el mismo interlocutor sale siempre igual, y dos
    // distintos rara vez coinciden.
    $tones = ['bg-amber-500', 'bg-emerald-600', 'bg-sky-600', 'bg-violet-600', 'bg-rose-600', 'bg-teal-600'];
    $tone = $user?->isAdmin() ? 'bg-gray-600' : $tones[crc32((string) $user?->name) % count($tones)];
@endphp

@if ($club?->crest_path)
    <span {{ $attributes->merge(['class' => $size.' shrink-0 overflow-hidden rounded-full bg-white ring-1 ring-black/10 dark:ring-white/15']) }}
          title="{{ $club->name }}">
        <img src="{{ Storage::disk(config('filesystems.uploads'))->url($club->crest_path) }}"
             alt="{{ $club->name }}" class="size-full object-contain p-0.5">
    </span>
@else
    <span {{ $attributes->merge(['class' => $size.' '.$text.' '.$tone.' flex shrink-0 items-center justify-center rounded-full font-bold text-white']) }}
          title="{{ $club?->name ?? $user?->name }}">
        {{ $initials }}
    </span>
@endif
