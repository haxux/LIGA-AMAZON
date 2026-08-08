@props(['heading', 'rows'])

<section class="mb-10 overflow-hidden rounded-lg border border-surface bg-surface">
    @if ($heading)
        <h2 class="font-display bg-surface-alt px-4 py-3 text-lg uppercase tracking-wide text-brand">{{ $heading }}</h2>
    @endif

    <table class="w-full text-left text-sm">
        <thead class="bg-surface-alt text-white/70">
            <tr>
                <th class="px-4 py-2">#</th>
                <th class="px-4 py-2">Equipo</th>
                <th class="px-4 py-2 text-center">PJ</th>
                <th class="px-4 py-2 text-center">G</th>
                <th class="px-4 py-2 text-center">E</th>
                <th class="px-4 py-2 text-center">P</th>
                <th class="px-4 py-2 text-center">GF</th>
                <th class="px-4 py-2 text-center">GC</th>
                <th class="px-4 py-2 text-center">DG</th>
                <th class="px-4 py-2 text-center">PTS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $row)
                <tr class="border-t border-surface-alt">
                    <td class="px-4 py-2">{{ $index + 1 }}</td>
                    <td class="px-4 py-2 font-medium">{{ $row->team->name }}</td>
                    <td class="px-4 py-2 text-center">{{ $row->played }}</td>
                    <td class="px-4 py-2 text-center">{{ $row->won }}</td>
                    <td class="px-4 py-2 text-center">{{ $row->drawn }}</td>
                    <td class="px-4 py-2 text-center">{{ $row->lost }}</td>
                    <td class="px-4 py-2 text-center">{{ $row->goals_for }}</td>
                    <td class="px-4 py-2 text-center">{{ $row->goals_against }}</td>
                    <td class="px-4 py-2 text-center">{{ $row->goal_difference }}</td>
                    <td class="px-4 py-2 text-center font-bold text-brand">{{ $row->points }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
