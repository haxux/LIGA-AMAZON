<x-layouts.site :title="'Noticias — Liga Amazon'">
    <h1 class="font-display mb-6 text-3xl uppercase tracking-wide">Noticias</h1>

    <div class="grid gap-6 md:grid-cols-3">
        @forelse ($items as $item)
            <x-site.news-card :item="$item" />
        @empty
            <p class="text-white/60">No hay noticias publicadas todavía.</p>
        @endforelse
    </div>
</x-layouts.site>
