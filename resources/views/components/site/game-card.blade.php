@props(['game'])

@php
    $isPlayed = $game->home_score !== null && $game->away_score !== null;
@endphp

<div class="flex items-center justify-between rounded-md border border-surface bg-surface px-4 py-3">
    <span class="flex-1 text-right font-medium">{{ $game->homeTeam->name }}</span>

    @if ($isPlayed)
        <span class="mx-4 font-display rounded bg-surface-alt px-3 py-1 text-lg font-bold text-brand">
            {{ $game->home_score }} – {{ $game->away_score }}
        </span>
    @else
        <span class="mx-4 font-display rounded bg-surface-alt px-3 py-1 text-sm text-white/70">vs</span>
    @endif

    <span class="flex-1 font-medium">{{ $game->awayTeam->name }}</span>
</div>
