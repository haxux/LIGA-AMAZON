@props(['item'])

<a href="{{ route('site.news.show', $item->slug) }}" class="block overflow-hidden rounded-md bg-surface transition hover:bg-surface-alt">
    @if ($item->cover_path)
        <img src="{{ Storage::disk(config('filesystems.uploads'))->url($item->cover_path) }}" alt="{{ $item->title }}" class="h-40 w-full bg-surface-muted object-cover">
    @else
        <div class="h-40 w-full bg-surface-muted bg-hatch"></div>
    @endif

    <div class="p-4">
        <div class="mb-2 font-mono text-[9px] tracking-[0.1em] text-brand">NOTICIA</div>
        <h3 class="font-display text-lg font-semibold uppercase tracking-wide text-white">{{ $item->title }}</h3>
        <p class="mt-2 font-mono text-[9px] text-white/40">{{ $item->published_at?->format('d/m/Y') }}</p>
    </div>
</a>
