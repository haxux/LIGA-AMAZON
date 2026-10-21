{{-- Una noticia en la lista y en la portada. Encima del título va el club del
     que trata, con su escudo —antes decía «NOTICIA», que ya se sabe—, o la
     liga si no es de ningún club.

     La foto, más alta y encuadrada desde arriba: casi todas son de un jugador,
     y recortadas a lo ancho le cortaban la cara. --}}
@props(['item'])

@php($club = $item->team?->club)

<a href="{{ route('site.news.show', $item->slug) }}"
   class="group flex flex-col overflow-hidden rounded-md bg-surface transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-brand">
    <div class="aspect-[4/3] overflow-hidden bg-surface-muted">
        @if ($item->cover_path)
            <img src="{{ Storage::disk(config('filesystems.uploads'))->url($item->cover_path) }}" alt="" loading="lazy"
                 class="size-full object-cover object-top">
        @else
            <div class="bg-hatch size-full"></div>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        <span class="mb-2 flex items-center gap-2 font-mono text-[10px] tracking-[0.1em] text-brand">
            @if ($club)
                <x-site.club-crest :club="$club" size="size-4" />
                {{ mb_strtoupper($club->name) }}
            @else
                LIGA
            @endif
        </span>

        <h3 class="font-display text-xl font-bold uppercase italic leading-tight text-white">{{ $item->title }}</h3>

        <p class="mt-auto pt-3 font-mono text-[10px] tracking-[0.08em] text-white/60">{{ $item->published_at?->format('d/m/Y') }}</p>
    </div>
</a>
