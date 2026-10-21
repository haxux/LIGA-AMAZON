@php
    $isPlayed = $game->home_score !== null && $game->away_score !== null;
    $matchday = $game->matchday;
    $stadium = $game->homeTeam?->club?->stadium;

    // El vocabulario de `GameEvent` está en inglés desde la Fase 2 y el sitio
    // público es español, así que la traducción vive aquí, como la de las
    // posiciones en la ficha de club.
    $labels = [
        \App\Models\GameEvent::TYPE_GOAL => 'Gol',
        \App\Models\GameEvent::TYPE_OWN_GOAL => 'Autogol',
        \App\Models\GameEvent::TYPE_ASSIST => 'Asistencia',
        \App\Models\GameEvent::TYPE_YELLOW_CARD => 'Tarjeta amarilla',
        \App\Models\GameEvent::TYPE_RED_CARD => 'Tarjeta roja',
        \App\Models\GameEvent::TYPE_CLEAN_SHEET => 'Portería a cero',
    ];

    $colors = [
        \App\Models\GameEvent::TYPE_GOAL => 'text-brand',
        \App\Models\GameEvent::TYPE_OWN_GOAL => 'text-rose-400',
        \App\Models\GameEvent::TYPE_ASSIST => 'text-white/70',
        \App\Models\GameEvent::TYPE_YELLOW_CARD => 'text-amber-400',
        \App\Models\GameEvent::TYPE_RED_CARD => 'text-rose-400',
        \App\Models\GameEvent::TYPE_CLEAN_SHEET => 'text-emerald-400',
    ];
@endphp

@php
    $cup = $game->cup();
@endphp

{{-- Un partido de copa internacional se ve dentro del escenario de su copa. --}}
<x-layouts.site :title="$game->homeTeam?->name.' – '.$game->awayTeam?->name.' — Liga Amazon'" :theme="$cup?->theme()">

    <div class="mb-4 font-mono text-[10px] tracking-[0.14em] text-ink/70">
        @if ($cup)
            <a href="{{ route('site.cups.show', $cup) }}" class="hover:text-ink">{{ mb_strtoupper($cup->name) }}</a>
            · {{ mb_strtoupper($game->competitionLabel()) }}
            @if ($cup->season) · {{ mb_strtoupper($cup->season->name) }} @endif
        @else
            <a href="{{ route('site.fixtures', ['temporada' => $matchday?->season_id]) }}" class="hover:text-ink">PARTIDOS</a>
            @if ($matchday?->division) · {{ mb_strtoupper($matchday->division->name) }} @endif
            @if ($matchday) · JORNADA {{ $matchday->number }} @endif
            @if ($matchday?->season) · {{ mb_strtoupper($matchday->season->name) }} @endif
        @endif
    </div>

    @php
        $winner = ! $isPlayed ? null : ($game->home_score > $game->away_score ? 'home' : ($game->home_score < $game->away_score ? 'away' : null));
        $sides = [
            ['team' => $game->homeTeam, 'score' => $game->home_score, 'key' => 'home'],
            ['team' => $game->awayTeam, 'score' => $game->away_score, 'key' => 'away'],
        ];
    @endphp

    {{-- (El negro de los bloques es fijo, `bg-black/60`, y no `bg-ink`: las
         copas internacionales redefinen `ink` con un tono claro.)

         El marcador, que es lo que se viene a ver: los escudos grandes, el
         resultado enorme en un bloque oscuro inclinado y, debajo, en qué
         estado está y cuándo y dónde se juega. El ganador va en blanco pleno;
         el perdedor, apagado; un lado perjudicado por una anulación, en rojo. --}}
    <section class="mb-6 overflow-hidden rounded-md bg-surface">
        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 px-4 py-6 sm:gap-6 sm:px-8 sm:py-8">
            @foreach ($sides as $index => $side)
                @if ($index === 1)
                    <div class="grid -skew-x-8 place-items-center rounded-[5px] bg-black/60 px-4 py-3 sm:px-6">
                        @if ($isPlayed)
                            <span class="font-display text-5xl font-extrabold leading-none tabular-nums text-brand [text-shadow:3px_3px_0_rgb(0_0_0/0.35)] sm:text-7xl">{{ $game->home_score }}<span class="px-1.5 text-white/25 sm:px-3">–</span>{{ $game->away_score }}</span>
                        @else
                            <span class="font-display text-3xl font-extrabold leading-none text-white/60 sm:text-5xl">{{ $game->kickoff_at ? $game->kickoff_at->format('H:i') : 'VS' }}</span>
                        @endif
                    </div>
                @endif

                @php($penalized = in_array($side['key'], $game->penalizedSides(), true))
                <a href="{{ route('site.clubs.show', ['club' => $side['team']?->club_id, 'temporada' => $matchday?->season_id]) }}"
                   class="flex min-w-0 flex-col items-center gap-2 text-center hover:opacity-85">
                    <x-site.team-crest :team="$side['team']" size="size-14 sm:size-20" />
                    <span @class([
                              'line-clamp-2 font-display text-lg font-extrabold uppercase italic leading-tight sm:text-2xl',
                              'text-rose-500' => $penalized,
                              'text-white' => ! $penalized && ($winner === null || $winner === $side['key']),
                              'text-white/55' => ! $penalized && $winner !== null && $winner !== $side['key'],
                          ])
                          @if ($penalized) title="Partido anulado en su contra" @endif>{{ $side['team']?->name }}</span>
                </a>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 border-t border-white/[0.06] bg-surface-alt px-4 py-3 font-mono text-[10px] tracking-[0.12em] text-white/60">
            @if ($game->isVoided())
                <span class="inline-grid -skew-x-12 rounded-[3px] bg-loss px-2.5 py-1"><span class="skew-x-12 font-bold text-white">ANULADO POR INCOMPARECENCIA ·
                    @if ($game->voided_against === 'home') EN CONTRA DE {{ mb_strtoupper($game->homeTeam?->name) }}
                    @elseif ($game->voided_against === 'away') EN CONTRA DE {{ mb_strtoupper($game->awayTeam?->name) }}
                    @else EN CONTRA DE AMBOS @endif
                </span></span>
            @else
                <span @class(['inline-grid -skew-x-12 rounded-[3px] px-2.5 py-1', 'bg-brand text-ink' => $isPlayed, 'bg-white/10 text-white/80' => ! $isPlayed])>
                    <span class="skew-x-12 font-bold">{{ $isPlayed ? 'JUGADO' : 'POR JUGAR' }}</span>
                </span>
            @endif
            @if ($game->kickoff_at) <span>{{ $game->kickoff_at->format('d/m/Y H:i') }}</span>
            @elseif ($matchday?->date) <span>{{ $matchday->date->format('d/m/Y') }}</span> @endif
            @if ($stadium) <span>{{ mb_strtoupper($stadium->name) }}</span> @endif
        </div>
    </section>

    {{-- El MVP del partido, si el administrador eligió uno. No es un evento:
         es el premio del partido entero, así que va aparte de la lista. --}}
    @if ($game->mvp)
        <a href="{{ route('site.players.show', $game->mvp) }}"
           class="mb-6 flex items-center gap-4 rounded-md bg-surface p-4 transition-colors hover:bg-surface-alt">
            <x-site.player-avatar :player="$game->mvp" size="size-12" initials class="text-base ring-2 ring-brand" />
            <span class="min-w-0 flex-1">
                <span class="inline-grid -skew-x-12 rounded-[3px] bg-brand px-2 py-0.5"><span class="skew-x-12 font-mono text-[10px] font-bold tracking-[0.14em] text-ink">MVP DEL PARTIDO</span></span>
                <span class="mt-1 block truncate font-display text-2xl font-extrabold uppercase italic text-white">{{ $game->mvp->name }}</span>
            </span>
            <span class="text-3xl text-brand" aria-hidden="true">★</span>
        </a>
    @endif

    <section class="rounded-md bg-surface p-4">
        <x-site.panel-title>EVENTOS</x-site.panel-title>

        @if ($events->isEmpty())
            <p class="text-sm text-white/55">
                Sin eventos registrados{{ $isPlayed ? ': sólo consta el resultado.' : ' todavía.' }}
            </p>
        @else
            {{-- Una línea de tiempo: el local a la izquierda y el visitante a la
                 derecha, como en el marcador, con el icono en el eje del medio.
                 Una fila por evento, en el orden en que se registraron. --}}
            <div class="mb-2 grid grid-cols-[minmax(0,1fr)_2.5rem_minmax(0,1fr)] items-center gap-3 border-b border-white/[0.06] pb-2">
                <span class="flex items-center justify-end gap-2 truncate font-mono text-[10px] tracking-[0.1em] text-white/60">
                    {{ mb_strtoupper($game->homeTeam?->name) }} <x-site.team-crest :team="$game->homeTeam" size="size-4" />
                </span>
                <span></span>
                <span class="flex items-center gap-2 truncate font-mono text-[10px] tracking-[0.1em] text-white/60">
                    <x-site.team-crest :team="$game->awayTeam" size="size-4" /> {{ mb_strtoupper($game->awayTeam?->name) }}
                </span>
            </div>

            <ol class="relative">
                <span class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-white/10" aria-hidden="true"></span>

                @foreach ($events as $entry)
                    @php($event = $entry['event'])
                    @php($isHome = $entry['side'] === 'home')
                    <li class="relative grid grid-cols-[minmax(0,1fr)_2.5rem_minmax(0,1fr)] items-center gap-3 py-1.5">
                        <div @class(['min-w-0', 'text-right' => $isHome, 'col-start-3' => ! $isHome, 'row-start-1' => true])>
                            <span class="block truncate font-display text-base font-bold text-white">
                                {{ $event->player?->name ?? 'Jugador retirado' }}
                            </span>
                            <span class="block font-mono text-[10px] tracking-[0.1em] {{ $event->isSecondYellow() ? 'text-rose-400' : ($colors[$event->type] ?? 'text-white/60') }}">
                                {{ $event->isSecondYellow() ? 'Doble amarilla · expulsado' : ($labels[$event->type] ?? $event->type) }}
                            </span>
                            @if ($event->type === \App\Models\GameEvent::TYPE_GOAL && $event->assist)
                                <span class="block truncate font-mono text-[10px] tracking-[0.1em] text-white/60">
                                    Asistencia: {{ $event->assist->player?->name ?? 'Jugador retirado' }}
                                </span>
                            @endif
                        </div>

                        {{-- El icono en lugar del minuto: el minuto ya no se
                             registra, y un balón o una tarjeta se leen de un vistazo. --}}
                        <span class="col-start-2 row-start-1 grid size-10 place-items-center rounded-full bg-black/60 text-white ring-2 ring-surface">
                            <x-event-icon :type="$event->type" :second-yellow="$event->isSecondYellow()" size="size-6" />
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Lo que dicen los técnicos del partido. Los lee cualquiera; comenta
         quien ha entrado como técnico. --}}
    @php($coachUser = auth('club')->user()?->isCoach() ? auth('club')->user() : null)
    @php($adminUser = auth()->user()?->isAdmin() ? auth()->user() : null)

    <section id="comentarios" class="mt-8 scroll-mt-24">
        <h2 class="mb-3 -skew-x-6 font-display text-2xl font-extrabold uppercase italic tracking-wide text-ink">
            COMENTARIOS DE LOS TÉCNICOS · {{ $game->comments->count() }}
        </h2>

        @if ($coachUser)
            <form method="POST" action="{{ route('site.games.comments.store', $game) }}" class="mb-4 rounded-md bg-surface p-4">
                @csrf
                <label for="comment-body" class="sr-only">Tu comentario</label>
                <textarea id="comment-body" name="body" rows="3" maxlength="{{ \App\Models\GameComment::MAX_LENGTH }}"
                          placeholder="¿Qué te pareció el partido?"
                          class="w-full resize-y rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 text-sm text-white placeholder:text-white/30 focus-visible:outline-2 focus-visible:outline-brand">{{ old('body') }}</textarea>

                @error('body')
                    <p class="mt-1 font-mono text-[10px] uppercase tracking-[0.1em] text-rose-400">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="font-mono text-[10px] tracking-[0.1em] text-white/60">
                        COMO {{ mb_strtoupper($coachUser->name) }}@if ($coachUser->club) · {{ mb_strtoupper($coachUser->club->short_name ?: $coachUser->club->name) }}@endif
                    </span>
                    <button type="submit"
                            class="rounded-[4px] bg-brand px-4 py-2 font-display text-sm font-bold uppercase tracking-[0.08em] text-ink hover:opacity-90">
                        Comentar
                    </button>
                </div>
            </form>
        @else
            <p class="mb-4 font-mono text-[11px] tracking-[0.08em] text-ink/70">
                Sólo los técnicos pueden comentar.
                <a href="{{ route('filament.club.auth.login') }}" class="underline hover:text-ink">Acceso técnicos</a>
            </p>
        @endif

        <div class="space-y-2">
            @forelse ($game->comments as $comment)
                <article class="flex gap-3 rounded-md bg-surface p-4" id="comentario-{{ $comment->id }}">
                    <x-site.club-crest :club="$comment->user?->club" size="size-[30px]" />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span class="font-display text-base font-semibold text-white">{{ $comment->user?->name ?? 'Técnico retirado' }}</span>
                            @if ($comment->user?->club)
                                <span class="font-mono text-[10px] tracking-[0.1em] text-brand">{{ mb_strtoupper($comment->user->club->short_name ?: $comment->user->club->name) }}</span>
                            @endif
                            <span class="font-mono text-[10px] tracking-[0.08em] text-white/60">{{ $comment->created_at?->diffForHumans() }}</span>
                        </div>

                        <p class="mt-1 whitespace-pre-line break-words text-sm leading-snug text-white/85">{{ $comment->body }}</p>
                    </div>

                    @if ($adminUser || ($coachUser && $coachUser->is($comment->user)))
                        <form method="POST" action="{{ route('site.comments.destroy', $comment) }}" onsubmit="return confirm('¿Borrar este comentario?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="font-mono text-[10px] tracking-[0.1em] text-white/60 hover:text-rose-400" title="Borrar comentario">BORRAR</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="rounded-md bg-surface px-4 py-5 text-center font-display text-base uppercase tracking-wide text-white/60">
                    Todavía no hay comentarios.
                </p>
            @endforelse
        </div>
    </section>
</x-layouts.site>
