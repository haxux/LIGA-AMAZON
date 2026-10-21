{{-- Una noticia destacada: la foto entera de fondo y el título en una placa
     sólida abajo a la izquierda, sin degradado. La usan la portada de
     Noticias y la de la liga. `large` es la grande; sin él, las de al lado.
     La altura la decide quien la usa, por `class`. --}}
@props(['item', 'large' => false])

@php($club = $item->team?->club)

<a href="{{ route('site.news.show', $item->slug) }}"
   {{ $attributes->merge(['class' => 'group relative block overflow-hidden rounded-md bg-surface-muted focus-visible:outline-2 focus-visible:outline-brand']) }}>
    @if ($item->cover_path)
        <img src="{{ Storage::disk(config('filesystems.uploads'))->url($item->cover_path) }}" alt=""
             class="absolute inset-0 size-full object-cover object-top">
    @else
        <div class="bg-hatch absolute inset-0"></div>
    @endif

    <span @class([
        'absolute bottom-0 left-0 max-w-[92%] rounded-tr-md bg-ink transition-colors group-hover:bg-surface',
        'px-5 py-4' => $large,
        'px-4 py-3' => ! $large,
    ])>
        <span class="mb-1.5 flex items-center gap-2 font-mono text-[10px] tracking-[0.1em] text-brand">
            @if ($club)
                <x-site.club-crest :club="$club" size="size-4" />
                {{ mb_strtoupper($club->name) }}
            @else
                LIGA
            @endif
            <span class="text-white/60">· {{ $item->published_at?->format('d/m/Y') }}</span>
        </span>
        <span @class([
            'block font-display font-extrabold uppercase italic leading-[1.05] text-white',
            'text-3xl sm:text-4xl' => $large,
            'text-xl' => ! $large,
        ])>{{ $item->title }}</span>
    </span>
</a>
