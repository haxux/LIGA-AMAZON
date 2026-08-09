@props(['heading', 'rows'])

<section class="mb-10 overflow-hidden rounded-md bg-surface">
    @if ($heading)
        <h2 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">{{ $heading }}</h2>
    @endif

    <table class="w-full text-left">
        <thead>
            <tr class="border-b border-white/10 font-mono text-[10px] tracking-[0.1em] text-white/60">
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">EQUIPO</th>
                <th class="px-4 py-2 text-center">PJ</th>
                <th class="px-4 py-2 text-center">G</th>
                <th class="px-4 py-2 text-center">E</th>
                <th class="px-4 py-2 text-center">P</th>
                <th class="px-4 py-2 text-center">GF</th>
                <th class="px-4 py-2 text-center">GC</th>
                <th class="px-4 py-2 text-center">DG</th>
                <th class="px-4 py-2 text-center text-brand">PTS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $row)
                <tr class="border-t border-white/[0.055] hover:bg-white/[0.045]">
                    <td class="px-4 py-2 font-mono text-xs text-white/50">{{ $index + 1 }}</td>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-3">
                            <x-site.team-crest :team="$row->team" />
                            <span class="font-display text-[19px] font-semibold tracking-[0.02em] text-white">{{ $row->team->name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-2 text-center text-white/70">{{ $row->played }}</td>
                    <td class="px-4 py-2 text-center text-white/70">{{ $row->won }}</td>
                    <td class="px-4 py-2 text-center text-white/70">{{ $row->drawn }}</td>
                    <td class="px-4 py-2 text-center text-white/70">{{ $row->lost }}</td>
                    <td class="px-4 py-2 text-center text-white/70">{{ $row->goals_for }}</td>
                    <td class="px-4 py-2 text-center text-white/70">{{ $row->goals_against }}</td>
                    <td class="px-4 py-2 text-center text-white/70">{{ $row->goal_difference }}</td>
                    <td class="px-4 py-2 text-center font-display text-lg font-bold text-brand">{{ $row->points }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="flex items-center gap-4 px-4 py-3 font-mono text-[10px] tracking-[0.06em] text-white/40">
        <span class="flex items-center gap-1.5">
            <span class="size-2 rounded-sm bg-brand"></span>
            CLASIFICACIÓN
        </span>
        <span class="flex items-center gap-1.5">
            <span class="size-2 rounded-sm bg-loss"></span>
            DESCENSO
        </span>
    </div>
</section>
