<x-layouts.site :title="'A Segunda — Liga Amazon'">
    <x-site.news-tabs current="wall" />

    <h1 class="font-display mb-1 -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">A Segunda</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/70">SÓLO TÉCNICOS Y PRESIDENTE · NO SE VE DESDE FUERA</div>

    <div class="mx-auto grid max-w-2xl gap-4">
        <x-site.wall-composer />

        @forelse ($posts as $post)
            <div class="rounded-md bg-surface">
                <x-site.wall-post :post="$post" :reader="$reader" class="!rounded-b-none" />

                @if ($post->replies->isNotEmpty())
                    <div class="border-t border-white/[0.06] px-4 pb-2 sm:px-5">
                        @if ($post->replies_count > $post->replies->count())
                            <a href="{{ route('site.wall.show', $post) }}"
                               class="block pt-3 font-mono text-[10px] tracking-[0.1em] text-brand hover:underline">
                                VER LAS {{ $post->replies_count }} RESPUESTAS
                            </a>
                        @endif
                        <div class="divide-y divide-white/[0.06] border-l-2 border-brand/40 pl-3">
                            {{-- Las dos últimas, de la más antigua a la más nueva. --}}
                            @foreach ($post->replies->reverse() as $reply)
                                <x-site.wall-post :post="$reply" :reader="$reader" compact />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-md bg-surface p-8 text-center">
                <div class="font-display text-2xl font-bold uppercase italic text-white">Todavía no hay nada</div>
                <p class="mt-1 text-sm text-white/60">Abre tú la conversación.</p>
            </div>
        @endforelse

        @if ($posts->hasPages())
            <nav class="flex justify-between font-display text-base font-bold uppercase italic" aria-label="Más publicaciones">
                @if ($posts->previousPageUrl())
                    <a href="{{ $posts->previousPageUrl() }}" class="rounded-[3px] bg-ink px-4 py-2 text-white hover:text-brand">← Más nuevas</a>
                @else
                    <span></span>
                @endif
                @if ($posts->nextPageUrl())
                    <a href="{{ $posts->nextPageUrl() }}" class="rounded-[3px] bg-ink px-4 py-2 text-white hover:text-brand">Más antiguas →</a>
                @endif
            </nav>
        @endif
    </div>

    @include('site.wall.script')
</x-layouts.site>
