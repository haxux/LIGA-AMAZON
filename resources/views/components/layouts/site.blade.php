<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'Liga Amazon') }}</title>

        {{-- El escudo de la liga. El .ico va primero porque es lo que pide un
             navegador que busca /favicon.ico a secas, y los marcadores. --}}
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-brand-weave font-sans text-ink">
        @php
            // El técnico se lee del guard `club`, el de su panel, que vive en la
            // misma sesión que el sitio: `auth()` a secas devuelve el guard del
            // administrador y aquí no diría nada.
            $coach = auth('club')->user();
            $coachClub = $coach?->isCoach() ? $coach->club : null;
            $coachSeason = app(App\Services\SeasonResolver::class)->active();

            // El chat es de técnicos Y presidentes, así que aquí valen las dos
            // puertas. Con las dos sesiones abiertas manda la del técnico, que
            // es la que el sitio usa para todo lo demás.
            $chatUser = $coach ?? auth()->user();
            $chatUnread = $chatUser ? App\Models\Conversation::unreadTotalFor($chatUser) : 0;

            $boundClub = request()->route('club');
            $boundClubId = is_object($boundClub) ? $boundClub->getKey() : $boundClub;

            // Estar en la ficha del club propio marca "Mi equipo" y no también
            // "Equipos": dos subrayados a la vez sólo emborronan dónde estás.
            $onOwnClub = $coachClub !== null
                && request()->routeIs('site.clubs.show')
                && (int) $boundClubId === (int) $coachClub->getKey();

            $nav = [
                ['label' => 'Clasificación', 'url' => route('site.standings'), 'active' => request()->routeIs('site.standings')],
                ['label' => 'Partidos', 'url' => route('site.fixtures'), 'active' => request()->routeIs('site.fixtures')],
                ['label' => 'Estadísticas', 'url' => route('site.scorers'), 'active' => request()->routeIs('site.scorers')],
                ['label' => 'Equipos', 'url' => route('site.clubs.index'), 'active' => request()->routeIs('site.clubs.*') && ! $onOwnClub],
                ['label' => 'Copas', 'url' => route('site.cups.index'), 'active' => request()->routeIs('site.cups.*')],
                ['label' => 'Noticias', 'url' => route('site.news.index'), 'active' => request()->routeIs('site.news.*')],
            ];

            // Sólo para el técnico: su club y su chat, a un clic desde
            // cualquier página del sitio. Para el resto de visitantes la
            // cabecera es exactamente la de siempre (design D14).
            if ($coachClub) {
                $nav[] = [
                    'coach' => true,
                    'label' => 'Mi equipo',
                    'url' => route('site.clubs.show', array_filter([
                        'club' => $coachClub->getKey(),
                        'temporada' => $coachSeason?->getKey(),
                    ])),
                    'active' => $onOwnClub,
                ];

            }

            // El chat cuelga de la sesión, no del rol: también el presidente
            // entra por aquí.
            if ($chatUser) {
                $nav[] = [
                    'coach' => true,
                    'label' => 'Chat',
                    'url' => route('site.chat'),
                    'active' => request()->routeIs('site.chat'),
                    'badge' => $chatUnread,
                ];
            }
        @endphp

        <header id="site-header" class="sticky top-0 z-40 bg-ink text-white transition-transform duration-300 will-change-transform">
            {{-- Tres zonas: marca, navegación y el club del técnico. La del
                 medio es la que cede —`min-w-0 flex-1`, y envuelve dentro de sí
                 misma— para que al entrar los enlaces del técnico el escudo no
                 salte a una segunda línea. --}}
            <div class="mx-auto flex max-w-6xl items-center gap-4 px-6 py-3 lg:gap-6">
                <a href="{{ route('site.standings') }}" class="flex shrink-0 items-baseline gap-2 font-display text-[22px] leading-none lg:text-[26px]">
                    <span class="font-extrabold text-brand">AMAZON</span>
                    <span class="font-semibold tracking-[0.14em] text-white">TOERNOOIEN</span>
                </a>

                <nav class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1 font-display text-sm font-semibold uppercase tracking-[0.08em] lg:gap-x-5 lg:text-[15px]">
                    @foreach ($nav as $item)
                        @if (! empty($item['coach']) && ($nav[$loop->index - 1]['coach'] ?? false) === false)
                            {{-- Lo del técnico, separado de lo que ve todo el
                                 mundo: se entiende de un vistazo que es suyo. --}}
                            <span class="hidden h-4 w-px shrink-0 bg-white/20 lg:block" aria-hidden="true"></span>
                        @endif

                        <a href="{{ $item['url'] }}"
                           class="flex items-center gap-1.5 whitespace-nowrap border-b-[3px] py-1.5 {{ $item['active'] ? 'border-brand text-white' : 'border-transparent text-white/72 hover:text-white' }}">
                            {{ $item['label'] }}

                            @if (($item['badge'] ?? 0) > 0)
                                {{-- Los no leídos, visibles desde cualquier
                                     página: es el aviso que el chat tiene, ya
                                     que no hay correos ni websockets. --}}
                                <span class="flex min-w-[1.15rem] items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold leading-[1.15rem] text-ink">
                                    {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                {{-- Sólo para el director técnico con sesión abierta: para
                     cualquier otro visitante la cabecera queda como estaba. --}}
                @if ($coachClub)
                    <a id="coach-club" href="{{ url('/club') }}"
                       class="flex shrink-0 items-center gap-2 rounded-[4px] border border-white/10 bg-white/[0.06] px-2.5 py-1.5 hover:bg-white/[0.12]"
                       title="Ir al panel de {{ $coachClub->name }}">
                        <x-site.club-crest :club="$coachClub" size="size-[20px]" />
                        {{-- El nombre se recorta en vez de empujar: en una
                             cabecera manda el encuadre, y el escudo ya
                             identifica al club. --}}
                        <span class="hidden max-w-[9rem] truncate font-display text-xs font-semibold uppercase tracking-[0.08em] text-white lg:block">
                            {{ $coachClub->short_name ?: $coachClub->name }}
                        </span>
                    </a>
                @endif
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            {{ $slot }}
        </main>

        <footer class="mt-10 bg-ink py-8 text-white/60">
            <div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-8 px-6">
                <div class="max-w-xs">
                    <div class="flex items-baseline gap-2 font-display text-xl">
                        <span class="font-extrabold text-brand">AMAZON</span>
                        <span class="font-semibold tracking-[0.14em] text-white">TOERNOOIEN</span>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed">Clasificación, partidos y goleadores de la liga.</p>
                </div>

                <div class="flex flex-wrap gap-10">
                    <div class="flex flex-col gap-2">
                        <span class="font-mono text-[9px] tracking-[0.14em] text-brand">COMPETICIÓN</span>
                        <a href="{{ route('site.standings') }}" class="text-sm text-white/60 hover:text-brand">Clasificación</a>
                        <a href="{{ route('site.fixtures') }}" class="text-sm text-white/60 hover:text-brand">Partidos</a>
                    </div>
                    <div class="flex flex-col gap-2">
                        <span class="font-mono text-[9px] tracking-[0.14em] text-brand">CLUBES</span>
                        <a href="{{ route('site.clubs.index') }}" class="text-sm text-white/60 hover:text-brand">Equipos</a>
                        <a href="{{ route('site.scorers') }}" class="text-sm text-white/60 hover:text-brand">Estadísticas</a>
                    </div>
                    <div class="flex flex-col gap-2">
                        <span class="font-mono text-[9px] tracking-[0.14em] text-brand">SITIO</span>
                        <a href="{{ route('site.news.index') }}" class="text-sm text-white/60 hover:text-brand">Noticias</a>
                        {{-- La puerta de entrada del técnico. Discreta a propósito:
                             el visitante no tiene nada que hacer aquí. --}}
                        <a href="{{ route('filament.club.auth.login') }}" class="text-sm text-white/60 hover:text-brand">Acceso técnicos</a>
                    </div>
                </div>
            </div>

            <div class="mx-auto mt-6 max-w-6xl border-t border-white/10 px-6 pt-4 font-mono text-[10px] tracking-[0.08em] text-white/40">
                &copy; {{ now()->year }} AMAZON TOERNOOIEN
            </div>
        </footer>
    </body>
</html>
