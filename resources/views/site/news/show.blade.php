<x-layouts.site :title="$item->title . ' — Liga Amazon'">
    <article class="mx-auto max-w-3xl">
        @if ($item->cover_path)
            <img src="{{ Storage::url($item->cover_path) }}" alt="{{ $item->title }}" class="mb-6 w-full rounded-lg bg-surface-muted object-cover">
        @endif

        <h1 class="font-display mb-2 text-3xl uppercase tracking-wide">{{ $item->title }}</h1>
        <p class="mb-6 text-sm text-white/60">{{ $item->published_at?->format('d/m/Y') }}</p>

        <div class="prose prose-invert max-w-none text-white/90">
            {!! nl2br(e($item->body)) !!}
        </div>
    </article>
</x-layouts.site>
