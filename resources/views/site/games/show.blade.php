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

@php($cup = $game->cup())

{{-- Un partido de copa internacional se ve dentro del escenario de su copa. --}}
<x-layouts.site :title="$game->homeTeam?->name.' – '.$game->awayTeam?->name.' — Liga Amazon'" :theme="$cup?->theme()">

    <div class="mb-4 font-mono text-[10px] tracking-[0.14em] text-ink/50">
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

    {{-- El marcador, que es lo que se viene a ver. --}}
    <section class="mb-6 rounded-[6px] border-t-[3px] border-brand bg-surface-alt p-6">
        <div class="flex items-center justify-between gap-4">
            @foreach ([['team' => $game->homeTeam, 'score' => $game->home_score, 'key' => 'home'], ['team' => $game->awayTeam, 'score' => $game->away_score, 'key' => 'away']] as $index => $side)
                @if ($index === 1)
                    <span class="font-mono text-xs tracking-[0.14em] text-white/40">{{ $isPlayed ? '–' : 'VS' }}</span>
                @endif

                <a href="{{ route('site.clubs.show', ['club' => $side['team']?->club_id, 'temporada' => $matchday?->season_id]) }}"
                   class="flex min-w-0 flex-1 flex-col items-center gap-2 text-center hover:opacity-80">
                    <x-site.team-crest :team="$side['team']" size="size-[48px]" />
                    <span class="truncate font-display text-xl font-semibold {{ in_array($side['key'], $game->penalizedSides()) ? 'text-rose-500' : 'text-white' }}">{{ $side['team']?->name }}</span>
                    <span class="font-display text-4xl font-bold {{ $isPlayed ? 'text-brand' : 'text-white/30' }}">
                        {{ $isPlayed ? $side['score'] : '–' }}
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1 border-t border-white/10 pt-3 font-mono text-[10px] tracking-[0.12em] text-white/45">
            @if ($game->isVoided())
                <span class="text-rose-400">ANULADO POR INCOMPARECENCIA ·
                    @if ($game->voided_against === 'home') EN CONTRA DE {{ mb_strtoupper($game->homeTeam?->name) }}
                    @elseif ($game->voided_against === 'away') EN CONTRA DE {{ mb_strtoupper($game->awayTeam?->name) }}
                    @else EN CONTRA DE AMBOS @endif
                </span>
            @else
                <span>{{ $isPlayed ? 'JUGADO' : 'POR JUGAR' }}</span>
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
           class="mb-6 flex items-center gap-4 rounded-[6px] border-l-[3px] border-brand bg-surface-alt p-4 hover:bg-surface-muted">
            <x-site.player-avatar :player="$game->mvp" />
            <span class="min-w-0 flex-1">
                <span class="block font-mono text-[10px] tracking-[0.14em] text-brand">MVP DEL PARTIDO</span>
                <span class="block truncate font-display text-xl font-semibold text-white">{{ $game->mvp->name }}</span>
            </span>
            <span class="text-xl" aria-hidden="true">★</span>
        </a>
    @endif

    <section>
        <h2 class="mb-3 inline-block rounded-[4px] bg-surface-alt px-3 py-1.5 font-mono text-[10px] tracking-[0.14em] text-brand">EVENTOS</h2>

        @forelse ($events as $entry)
            @php($event = $entry['event'])
            @php($isHome = $entry['side'] === 'home')

            {{-- El local a la izquierda y el visitante a la derecha, como en el
                 marcador: quién lo hizo se lee del lado, no de una etiqueta. --}}
            <div class="mb-2 flex items-center gap-3 rounded-[5px] bg-surface-alt px-4 py-2 {{ $isHome ? '' : 'flex-row-reverse text-right' }}">
                {{-- El icono en lugar del minuto: el minuto ya no se registra, y
                     un balón o una tarjeta se leen de un vistazo. --}}
                <x-event-icon :type="$event->type" :second-yellow="$event->isSecondYellow()" size="size-6" />

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-base font-semibold text-white">
                        {{ $event->player?->name ?? 'Jugador retirado' }}
                    </span>
                    <span class="block font-mono text-[10px] tracking-[0.1em] {{ $event->isSecondYellow() ? 'text-rose-400' : ($colors[$event->type] ?? 'text-white/60') }}">
                        {{ $event->isSecondYellow() ? 'Doble amarilla · expulsado' : ($labels[$event->type] ?? $event->type) }}
                    </span>
                    @if ($event->type === \App\Models\GameEvent::TYPE_GOAL && $event->assist)
                        <span class="block font-mono text-[10px] tracking-[0.1em] text-white/45">
                            Asistencia: {{ $event->assist->player?->name ?? 'Jugador retirado' }}
                        </span>
                    @endif
                </span>
            </div>
        @empty
            <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
                Sin eventos registrados{{ $isPlayed ? ': sólo consta el resultado.' : ' todavía.' }}
            </p>
        @endforelse
    </section>

    {{-- Lo que dicen los técnicos del partido. Los lee cualquiera; comenta
         quien ha entrado como técnico. --}}
    @php($coachUser = auth('club')->user()?->isCoach() ? auth('club')->user() : null)
    @php($adminUser = auth()->user()?->isAdmin() ? auth()->user() : null)

    <section id="comentarios" class="mt-8 scroll-mt-24">
        <h2 class="mb-3 inline-block rounded-[4px] bg-surface-alt px-3 py-1.5 font-mono text-[10px] tracking-[0.14em] text-brand">
            COMENTARIOS DE LOS TÉCNICOS · {{ $game->comments->count() }}
        </h2>

        @if ($coachUser)
            <form method="POST" action="{{ route('site.games.comments.store', $game) }}" class="mb-4 rounded-[6px] bg-surface-alt p-4">
                @csrf
                <label for="comment-body" class="sr-only">Tu comentario</label>
                <textarea id="comment-body" name="body" rows="3" maxlength="{{ \App\Models\GameComment::MAX_LENGTH }}"
                          placeholder="¿Qué te pareció el partido?"
                          class="w-full resize-y rounded-[4px] border border-white/10 bg-surface px-3 py-2 text-sm text-white placeholder:text-white/30">{{ old('body') }}</textarea>

                @error('body')
                    <p class="mt-1 font-mono text-[10px] uppercase tracking-[0.1em] text-rose-400">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="font-mono text-[10px] tracking-[0.1em] text-white/40">
                        COMO {{ mb_strtoupper($coachUser->name) }}@if ($coachUser->club) · {{ mb_strtoupper($coachUser->club->short_name ?: $coachUser->club->name) }}@endif
                    </span>
                    <button type="submit"
                            class="rounded-[4px] bg-brand px-4 py-2 font-display text-sm font-bold uppercase tracking-[0.08em] text-ink hover:opacity-90">
                        Comentar
                    </button>
                </div>
            </form>
        @else
            <p class="mb-4 font-mono text-[11px] tracking-[0.08em] text-ink/60">
                Sólo los técnicos pueden comentar.
                <a href="{{ route('filament.club.auth.login') }}" class="underline hover:text-ink">Acceso técnicos</a>
            </p>
        @endif

        <div class="space-y-2">
            @forelse ($game->comments as $comment)
                <article class="flex gap-3 rounded-[6px] bg-surface-alt p-4" id="comentario-{{ $comment->id }}">
                    <x-site.club-crest :club="$comment->user?->club" size="size-[30px]" />

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span class="font-display text-base font-semibold text-white">{{ $comment->user?->name ?? 'Técnico retirado' }}</span>
                            @if ($comment->user?->club)
                                <span class="font-mono text-[10px] tracking-[0.1em] text-brand">{{ mb_strtoupper($comment->user->club->short_name ?: $comment->user->club->name) }}</span>
                            @endif
                            <span class="font-mono text-[10px] tracking-[0.08em] text-white/35">{{ $comment->created_at?->diffForHumans() }}</span>
                        </div>

                        <p class="mt-1 whitespace-pre-line break-words text-sm leading-snug text-white/85">{{ $comment->body }}</p>
                    </div>

                    @if ($adminUser || ($coachUser && $coachUser->is($comment->user)))
                        <form method="POST" action="{{ route('site.comments.destroy', $comment) }}" onsubmit="return confirm('¿Borrar este comentario?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="font-mono text-[10px] tracking-[0.1em] text-white/35 hover:text-rose-400" title="Borrar comentario">BORRAR</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="rounded-md bg-surface px-4 py-5 text-center font-display text-base uppercase tracking-wide text-white/50">
                    Todavía no hay comentarios.
                </p>
            @endforelse
        </div>
    </section>
</x-layouts.site>
