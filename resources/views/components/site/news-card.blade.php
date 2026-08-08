@props(['item'])

<a href="{{ route('site.news.show', $item->slug) }}" class="block overflow-hidden rounded-lg border border-surface bg-surface transition hover:border-brand">
    @if ($item->cover_path)
        <img src="{{ Storage::url($item->cover_path) }}" alt="{{ $item->title }}" class="h-40 w-full bg-surface-muted object-cover">
    @endif

    <div class="p-4">
        <h3 class="font-display text-lg uppercase tracking-wide">{{ $item->title }}</h3>
        <p class="mt-1 text-xs text-white/60">{{ $item->published_at?->format('d/m/Y') }}</p>
    </div>
</a>
