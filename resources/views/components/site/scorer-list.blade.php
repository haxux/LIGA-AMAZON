@props(['title', 'rows'])

<section class="mb-10 overflow-hidden rounded-lg border border-surface bg-surface">
    <h2 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">{{ $title }}</h2>

    <ol class="divide-y divide-surface-alt">
        @forelse ($rows as $index => $row)
            <li class="flex items-center justify-between px-4 py-2 text-sm">
                <span>
                    <span class="text-white/50">{{ $index + 1 }}.</span>
                    <span class="font-medium">{{ $row->player->name }}</span>
                    <span class="text-white/50">({{ $row->player->team->short_name }})</span>
                </span>
                <span class="font-display font-bold text-brand">{{ $row->count }}</span>
            </li>
        @empty
            <li class="px-4 py-2 text-sm text-white/60">Sin datos todavía.</li>
        @endforelse
    </ol>
</section>
