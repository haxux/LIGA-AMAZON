@props(['heading', 'rows'])

{{-- Los tres primeros de una división, como las tablas de Estadísticas: el
     líder aparte y en grande, con sus puntos enormes, y el segundo y el
     tercero compactos con su medalla de plata y bronce. --}}
@php
    $rows = collect($rows)->values();
    $leader = $rows->first();
    $medals = [2 => 'bg-[#C9D1DB] text-ink', 3 => 'bg-[#CD8A4E] text-ink'];
    $line = fn ($row) => $row->played.' PJ · '.($row->goal_difference > 0 ? '+' : '').$row->goal_difference.' DG';
@endphp

<section class="overflow-hidden rounded-md bg-surface">
    @if ($heading)
        <h3 class="flex items-center gap-3 bg-surface-alt px-4 py-2.5">
            <span class="grid -skew-x-12 place-items-center rounded-[3px] bg-brand px-2.5 py-1 text-ink" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="size-5 skew-x-12" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                    <path d="M12 2.8l7 2.5V11c0 4.7-3 8.1-7 10-4-1.9-7-5.3-7-10V5.3z" />
                </svg>
            </span>
            <span class="font-display text-lg font-bold uppercase italic tracking-wide text-white">{{ $heading }}</span>
        </h3>
    @endif

    @if ($leader)
        <a href="{{ route('site.clubs.show', ['club' => $leader->team->club_id]) }}"
           class="flex items-center gap-4 border-b border-white/[0.06] px-4 py-4 transition-colors hover:bg-surface-alt">
            <span class="relative shrink-0">
                <x-site.team-crest :team="$leader->team" size="size-14" />
                <span class="absolute -right-1.5 -bottom-1 grid size-6 -skew-x-12 place-items-center rounded-[3px] bg-brand font-display text-sm font-bold text-ink"
                      title="Puesto 1"><span class="skew-x-12">1</span></span>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-display text-2xl font-extrabold uppercase italic leading-tight text-white">{{ $leader->team->name }}</span>
                <span class="block font-mono text-[10px] tracking-[0.06em] text-white/60">{{ $line($leader) }}</span>
            </span>
            <span class="text-right">
                <span class="block -skew-x-8 font-display text-5xl font-extrabold leading-none tabular-nums text-brand [text-shadow:3px_3px_0_rgb(0_0_0/0.35)]">{{ $leader->points }}</span>
                <span class="font-mono text-[9px] tracking-[0.12em] text-white/60">PTS</span>
            </span>
        </a>

        <ol class="divide-y divide-white/[0.04]">
            @foreach ($rows->slice(1) as $index => $row)
                <li>
                    <a href="{{ route('site.clubs.show', ['club' => $row->team->club_id]) }}"
                       class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-surface-alt">
                        <span class="grid h-5 w-6 shrink-0 -skew-x-12 place-items-center rounded-[3px] font-display text-xs font-bold {{ $medals[$index + 1] ?? 'text-white/55' }}">
                            <span class="skew-x-12">{{ $index + 1 }}</span>
                        </span>
                        <x-site.team-crest :team="$row->team" size="size-6" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-display text-base font-bold text-white">{{ $row->team->name }}</span>
                            <span class="block font-mono text-[10px] tracking-[0.06em] text-white/60">{{ $line($row) }}</span>
                        </span>
                        <span class="-skew-x-8 font-display text-2xl font-extrabold tabular-nums text-white">{{ $row->points }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    @endif
</section>
