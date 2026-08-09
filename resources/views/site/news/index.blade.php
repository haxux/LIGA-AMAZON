<x-layouts.site :title="'Noticias — Liga Amazon'">
    <h1 class="font-display mb-1 text-3xl uppercase tracking-wide text-ink">Noticias</h1>
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/50">LATEST NEWS</div>

    <div class="grid gap-6 md:grid-cols-3">
        @forelse ($items as $item)
            <x-site.news-card :item="$item" />
        @empty
            <p class="text-ink/70">No hay noticias publicadas todavía.</p>
        @endforelse
    </div>
</x-layouts.site>
