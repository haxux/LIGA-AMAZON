{{-- Una publicación de «A Segunda»: autor con el escudo de su club (o
     «Presidente»), hace cuánto, el texto, las fotos y la barra de abajo con
     «me gusta», «no me gusta», respuestas y borrar.

     `compact` es una respuesta dentro de un hilo: más pequeña y sin fotos en
     rejilla grande. --}}
@props(['post', 'reader', 'compact' => false, 'link' => true])

@php
    $author = $post->author;
    $club = $author?->isCoach() ? $author->club : null;
    $role = $author?->isAdmin() ? 'PRESIDENTE' : ($club ? mb_strtoupper($club->short_name ?: $club->name) : '');
    $mine = $post->myReaction();
    $mayDelete = app(App\Services\WallService::class)->mayDelete($reader, $post);
    $images = $post->images;
@endphp

<article id="publicacion-{{ $post->getKey() }}"
         {{ $attributes->merge(['class' => 'scroll-mt-24 '.($compact ? 'py-3' : 'rounded-md bg-surface p-4 sm:p-5')]) }}>
    <header class="flex items-center gap-3">
        @if ($club)
            <x-site.club-crest :club="$club" :size="$compact ? 'size-7' : 'size-10'" />
        @else
            <span @class(['grid shrink-0 place-items-center rounded-full bg-brand/15 font-display font-bold text-brand', 'size-7 text-xs' => $compact, 'size-10 text-sm' => ! $compact])>P</span>
        @endif

        <span class="min-w-0 flex-1">
            <span class="block truncate font-display font-bold italic leading-tight text-white {{ $compact ? 'text-sm' : 'text-lg' }}">{{ $author?->name ?? 'Cuenta borrada' }}</span>
            <span class="block truncate font-mono text-[10px] tracking-[0.08em] text-white/60">
                {{ $role }}@if ($role) · @endif<time datetime="{{ $post->created_at?->toIso8601String() }}" title="{{ $post->created_at?->format('d/m/Y H:i') }}">{{ $post->created_at?->locale('es')->diffForHumans() }}</time>
            </span>
        </span>
    </header>

    @if ($post->body)
        <p class="mt-3 whitespace-pre-line break-words leading-relaxed text-white/90 {{ $compact ? 'text-sm' : 'text-[15px]' }}">{{ $post->body }}</p>
    @endif

    @if ($images->isNotEmpty())
        {{-- Una foto entera; dos a cuatro, en rejilla. Al tocarla se ve en
             grande (el visor es de la página, ver site/wall). --}}
        <div @class([
            'mt-3 grid gap-1 overflow-hidden rounded-md',
            'grid-cols-2' => $images->count() > 1,
            'max-w-xs' => $compact,
        ])>
            @foreach ($images as $image)
                <button type="button" data-wall-photo="{{ $image->url() }}"
                        @class([
                            'block overflow-hidden bg-surface-muted focus-visible:outline-2 focus-visible:outline-brand',
                            'aspect-square' => $images->count() > 1,
                            'col-span-2 aspect-[4/3]' => $images->count() === 3 && $loop->first,
                        ])
                        aria-label="Ver la foto en grande">
                    <img src="{{ $image->url() }}" alt="" loading="lazy" width="{{ $image->width }}" height="{{ $image->height }}"
                         @class(['size-full object-cover', 'max-h-[520px]' => $images->count() === 1])>
                </button>
            @endforeach
        </div>
    @endif

    <footer class="mt-3 flex items-center gap-1 text-white/70">
        @foreach ([1 => ['Me gusta', $post->likes_count, 'M7 10v10H4V10zM7 10l4-7a2 2 0 0 1 3 2l-1 5h6a2 2 0 0 1 2 2.3l-1.3 6A2 2 0 0 1 17.7 20H7'],
                   -1 => ['No me gusta', $post->dislikes_count, 'M17 14V4h3v10zM17 14l-4 7a2 2 0 0 1-3-2l1-5H5a2 2 0 0 1-2-2.3l1.3-6A2 2 0 0 1 6.3 4H17']] as $value => [$label, $count, $icon])
            <button type="button" data-wall-react="{{ route('site.wall.react', $post) }}" data-value="{{ $value }}"
                    aria-pressed="{{ $mine === $value ? 'true' : 'false' }}" aria-label="{{ $label }}" title="{{ $label }}"
                    class="flex items-center gap-1.5 rounded-[4px] px-2 py-1.5 transition-colors hover:bg-white/[0.06] hover:text-white aria-pressed:text-brand focus-visible:outline-2 focus-visible:outline-brand">
                <svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                <span data-count class="font-display text-sm font-bold tabular-nums">{{ $count }}</span>
            </button>
        @endforeach

        @if (! $post->isReply() && $link)
            <a href="{{ route('site.wall.show', $post) }}#responder"
               class="flex items-center gap-1.5 rounded-[4px] px-2 py-1.5 transition-colors hover:bg-white/[0.06] hover:text-white">
                <svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true"><path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-7l-5 4v-4H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" /></svg>
                <span class="font-display text-sm font-bold tabular-nums">{{ $post->replies_count }}</span>
                <span class="sr-only">respuestas</span>
            </a>
        @endif

        @if ($mayDelete)
            <form method="POST" action="{{ route('site.wall.destroy', $post) }}" class="ml-auto"
                  onsubmit="return confirm('¿Borrar esta publicación?{{ $post->isReply() ? '' : ' Se borran también sus respuestas.' }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-[4px] px-2 py-1.5 font-mono text-[10px] tracking-[0.1em] text-white/60 hover:bg-white/[0.06] hover:text-rose-300">BORRAR</button>
            </form>
        @endif
    </footer>
</article>
