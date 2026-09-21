@props(['game', 'matchdayNumber' => null])

@php
    $isPlayed = $game->home_score !== null && $game->away_score !== null;
    $number = $matchdayNumber ?? $game->matchday->number;
@endphp

<a href="{{ route('site.games.show', $game) }}"
   class="block rounded-[5px] border-t-[3px] border-brand bg-surface-alt p-4 hover:bg-surface-muted">
    <div class="mb-3 font-mono text-[9px] tracking-[0.1em] text-white/40">JORNADA {{ $number }}</div>

    <div class="flex items-center justify-between gap-2">
        <span class="flex min-w-0 items-center gap-2">
            <x-site.team-crest :team="$game->homeTeam" size="size-[18px]" />
            <span class="truncate font-display text-lg font-semibold text-white">{{ $game->homeTeam->name }}</span>
        </span>
        <span class="font-display text-lg font-bold {{ $isPlayed ? 'text-brand' : 'text-white/40' }}">
            {{ $isPlayed ? $game->home_score : '–' }}
        </span>
    </div>

    <div class="mt-2 flex items-center justify-between gap-2">
        <span class="flex min-w-0 items-center gap-2">
            <x-site.team-crest :team="$game->awayTeam" size="size-[18px]" />
            <span class="truncate font-display text-lg font-semibold text-white">{{ $game->awayTeam->name }}</span>
        </span>
        <span class="font-display text-lg font-bold {{ $isPlayed ? 'text-brand' : 'text-white/40' }}">
            {{ $isPlayed ? $game->away_score : '–' }}
        </span>
    </div>
</a>
