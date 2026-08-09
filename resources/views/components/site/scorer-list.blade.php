@props(['title', 'rows'])

<section class="mb-10 overflow-hidden rounded-md bg-surface">
    <h2 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">{{ $title }}</h2>

    <ol class="divide-y divide-white/[0.055]">
        @forelse ($rows as $index => $row)
            <li class="flex items-center gap-3 px-4 py-2">
                <span class="w-5 font-mono text-xs text-white/40">{{ $index + 1 }}</span>
                <x-site.player-avatar :player="$row->player" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-base font-semibold text-white">{{ $row->player->name }}</span>
                    <span class="block font-mono text-[10px] tracking-[0.06em] text-white/40">{{ $row->player->team->short_name }}</span>
                </span>
                <span class="font-display text-[22px] font-bold text-brand">{{ $row->count }}</span>
            </li>
        @empty
            <li class="px-4 py-2 text-sm text-white/60">Sin datos todavía.</li>
        @endforelse
    </ol>
</section>
