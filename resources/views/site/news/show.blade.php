@php
    // El mes en español aquí: `app.locale` es inglés (ver la lista).
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $date = $item->published_at;

    // El cuerpo es texto plano escrito en el panel: los párrafos son los
    // bloques separados por una línea en blanco. Uno que empieza con comillas
    // es una cita —la carta de un club, la frase de un jugador— y se destaca.
    $paragraphs = collect(preg_split('/\R\s*\R/u', trim((string) $item->body)))
        ->map(fn ($text) => trim($text))
        ->filter()
        ->values();
    $isQuote = fn (string $text) => preg_match('/^["“«]/u', $text) === 1;
@endphp

<x-layouts.site :title="$item->title . ' — Liga Amazon'">
    <article class="mx-auto max-w-4xl">
        @if ($item->cover_path)
            <img src="{{ Storage::disk(config('filesystems.uploads'))->url($item->cover_path) }}" alt="{{ $item->title }}"
                 class="max-h-[560px] w-full rounded-t-md bg-surface-muted object-cover object-top">
        @endif

        {{-- La cabecera, pegada a la foto: de qué club es (con su ficha a un
             toque), el título en grande y la fecha que se lee. --}}
        <header @class(['bg-surface px-5 py-5 sm:px-8 sm:py-6', 'rounded-b-md' => $item->cover_path, 'rounded-md' => ! $item->cover_path])>
            @if ($club)
                <a href="{{ route('site.clubs.show', ['club' => $club->id]) }}"
                   class="mb-3 inline-flex -skew-x-12 items-center gap-2 rounded-[3px] bg-brand px-3 py-1 text-ink hover:bg-white">
                    <span class="flex skew-x-12 items-center gap-2 font-display text-sm font-bold uppercase italic tracking-[0.06em]">
                        <x-site.club-crest :club="$club" size="size-4" />
                        {{ $club->name }}
                    </span>
                </a>
            @endif

            <h1 class="font-display -skew-x-6 text-3xl font-extrabold uppercase italic leading-[1.05] tracking-wide text-white sm:text-5xl">{{ $item->title }}</h1>

            @if ($date)
                <p class="mt-3 font-mono text-[11px] tracking-[0.1em] text-white/50">
                    {{ mb_strtoupper($date->day.' de '.$months[$date->month - 1].' de '.$date->year) }}
                </p>
            @endif
        </header>

        {{-- El texto, en una columna de lectura: letra más grande, aire entre
             líneas y el primer párrafo un punto más grande, como una entradilla. --}}
        <div class="mx-auto mt-6 max-w-[68ch] rounded-md bg-surface px-5 py-6 sm:px-8 sm:py-8">
            @foreach ($paragraphs as $text)
                @if ($isQuote($text))
                    <blockquote class="my-6 border-l-4 border-brand pl-5 font-display text-xl italic leading-snug text-white sm:text-2xl">
                        {!! nl2br(e($text)) !!}
                    </blockquote>
                @else
                    <p @class([
                        'leading-[1.75]',
                        'text-lg text-white sm:text-xl' => $loop->first,
                        'text-base text-white/85 sm:text-[17px]' => ! $loop->first,
                        'mb-5' => ! $loop->last,
                    ])>{!! nl2br(e($text)) !!}</p>
                @endif
            @endforeach
        </div>
    </article>

    {{-- Para seguir leyendo: lo último del mismo club, o de la liga. --}}
    @if ($more->isNotEmpty())
        <section class="mx-auto mt-12 max-w-6xl">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <h2 class="-skew-x-6 font-display text-2xl font-extrabold uppercase italic tracking-wide text-ink">
                    {{ $moreFromClub ? 'Más de '.$club->name : 'Más noticias' }}
                </h2>
                <a href="{{ route('site.news.index') }}"
                   class="font-display text-sm font-bold uppercase tracking-[0.08em] text-ink/70 hover:text-ink">Todas las noticias</a>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($more as $other)
                    <x-site.news-card :item="$other" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.site>
