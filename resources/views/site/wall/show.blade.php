<x-layouts.site :title="'A Segunda — Liga Amazon'">
    <x-site.news-tabs current="wall" />

    <div class="mx-auto grid max-w-2xl gap-4">
        <a href="{{ route('site.wall.index') }}" class="font-mono text-[10px] tracking-[0.14em] text-ink/70 hover:text-ink">← A SEGUNDA</a>

        <div class="rounded-md bg-surface">
            <x-site.wall-post :post="$post" :reader="$reader" :link="false" class="!rounded-b-none" />

            <div class="border-t border-white/[0.06] px-4 pb-2 sm:px-5">
                <div class="pt-3 font-mono text-[10px] tracking-[0.1em] text-white/60">
                    {{ $post->replies_count }} {{ $post->replies_count === 1 ? 'RESPUESTA' : 'RESPUESTAS' }}
                </div>
                @if ($post->replies->isNotEmpty())
                    <div class="divide-y divide-white/[0.06] border-l-2 border-brand/40 pl-3">
                        @foreach ($post->replies as $reply)
                            <x-site.wall-post :post="$reply" :reader="$reader" compact />
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <x-site.wall-composer id="responder" :parent="$post" placeholder="Responde a {{ $post->author?->name ?? 'esta publicación' }}…" class="scroll-mt-24" />
    </div>

    @include('site.wall.script')
</x-layouts.site>
