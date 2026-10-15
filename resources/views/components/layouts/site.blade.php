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

        {{-- Notificaciones push de partidos: ver public/js/push.js y
             App\Http\Controllers\PushSubscriptionController. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
        <x-push-script />
    </head>
    {{-- Columna a pantalla completa: el contenido crece (`flex-1`) y empuja el
         pie al fondo. Con una página corta —una clasificación con pocos
         equipos— el pie quedaba a media pantalla y debajo se veía el fondo
         amarillo vacío. --}}
    <body class="flex min-h-screen flex-col bg-brand-weave font-sans text-ink">
        @php
            // El técnico se lee del guard `club`, el de su panel, que vive en la
            // misma sesión que el sitio: `auth()` a secas devuelve el guard del
            // administrador y aquí no diría nada.
            $coach = auth('club')->user();
            $coachClub = $coach?->isCoach() ? $coach->club : null;
            $coachSeason = app(App\Services\SeasonResolver::class)->active();

            // El administrador que mira el sitio público: un botón para volver
            // a su panel. Y quien no ha entrado de ninguna manera: un icono
            // para hacerlo, que antes estaba sólo en el pie.
            $adminUser = auth()->user()?->isAdmin() ? auth()->user() : null;
            $isGuest = $coachClub === null && $adminUser === null;

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
                // Historia cuelga de Clasificación, así que resalta con ella:
                // quien está mirando una tabla de 2025 sigue estando en la
                // sección de las tablas.
                ['label' => 'Clasificación', 'url' => route('site.standings'), 'active' => request()->routeIs('site.standings') || request()->routeIs('site.history')],
                ['label' => 'Partidos', 'url' => route('site.fixtures'), 'active' => request()->routeIs('site.fixtures')],
                ['label' => 'Estadísticas', 'url' => route('site.scorers'), 'active' => request()->routeIs('site.scorers')],
                ['label' => 'Equipos', 'url' => route('site.clubs.index'), 'active' => request()->routeIs('site.clubs.*') && ! $onOwnClub],
                // Copas abre un menú: las de casa y las internacionales viven en
                // listados distintos.
                ['label' => 'Copas', 'url' => route('site.cups.index'), 'active' => request()->routeIs('site.cups.*'), 'children' => [
                    ['label' => 'Copas nacionales', 'url' => route('site.cups.index', ['tipo' => 'nacionales']), 'active' => request()->routeIs('site.cups.index') && request('tipo') === 'nacionales'],
                    ['label' => 'Copas internacionales', 'url' => route('site.cups.index', ['tipo' => 'internacionales']), 'active' => request()->routeIs('site.cups.index') && request('tipo') === 'internacionales'],
                ]],
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
            {{-- En el móvil la cabecera es una sola fila —marca, club y botón de
                 menú— y los enlaces se despliegan debajo, en vertical: puestos
                 en línea envolvían en tres renglones y empujaban todo hacia
                 abajo. Desde `lg` vuelve a ser la barra de siempre. --}}
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-3 px-6 py-3 lg:flex-nowrap lg:gap-x-6">
                {{-- A la portada, que es la convención de cualquier sitio y lo
                     que el propietario pidió: la marca da la bienvenida. --}}
                <a href="{{ route('site.home') }}" class="order-1 mr-auto flex shrink-0 items-baseline gap-2 font-display text-[22px] leading-none lg:mr-0 lg:text-[26px]">
                    <span class="font-extrabold text-brand">AMAZON</span>
                    <span class="font-semibold tracking-[0.14em] text-white">TOERNOOIEN</span>
                </a>

                {{-- El botón del menú, sólo en el móvil. Lleva el aviso de no
                     leídos porque el enlace del chat queda dentro, escondido. --}}
                <button type="button" id="site-menu-toggle" aria-controls="site-nav" aria-expanded="false" aria-label="Abrir el menú"
                        class="relative order-3 flex size-10 shrink-0 items-center justify-center rounded-[4px] border border-white/10 bg-white/[0.06] hover:bg-white/[0.12] lg:hidden">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    @if ($chatUnread > 0)
                        <span class="absolute -right-1 -top-1 size-3 rounded-full border-2 border-ink bg-emerald-500" aria-hidden="true"></span>
                    @endif
                </button>

                <nav id="site-nav" class="order-4 mt-3 hidden w-full flex-col gap-y-0.5 border-t border-white/10 pt-2 font-display text-base font-semibold uppercase tracking-[0.08em] data-open:flex lg:order-2 lg:mt-0 lg:flex lg:w-auto lg:min-w-0 lg:flex-1 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-5 lg:gap-y-1 lg:border-0 lg:pt-0 lg:text-[15px]">
                    @foreach ($nav as $item)
                        @if (! empty($item['coach']) && ($nav[$loop->index - 1]['coach'] ?? false) === false)
                            {{-- Lo del técnico, separado de lo que ve todo el
                                 mundo: se entiende de un vistazo que es suyo. --}}
                            <span class="hidden h-4 w-px shrink-0 bg-white/20 lg:block" aria-hidden="true"></span>
                        @endif

                        @if (! empty($item['children']))
                            {{-- Un <details> y no un menú por hover: se abre con
                                 un toque en el móvil y funciona sin JavaScript. --}}
                            <details class="site-nav-menu group lg:relative">
                                <summary class="flex cursor-pointer list-none items-center gap-1 whitespace-nowrap border-l-[3px] py-2.5 pl-3 lg:border-l-0 lg:border-b-[3px] lg:py-1.5 lg:pl-0 [&::-webkit-details-marker]:hidden {{ $item['active'] ? 'border-brand text-white' : 'border-transparent text-white/72 hover:text-white' }}">
                                    {{ $item['label'] }}
                                    <svg class="size-3 transition-transform group-open:rotate-180" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5"/></svg>
                                </summary>

                                <div class="ml-3 border-l border-white/10 lg:absolute lg:left-0 lg:top-full lg:z-50 lg:ml-0 lg:mt-2 lg:min-w-[14rem] lg:overflow-hidden lg:rounded-[5px] lg:border lg:bg-ink lg:shadow-xl">
                                    @foreach ($item['children'] as $child)
                                        <a href="{{ $child['url'] }}"
                                           class="block border-l-[3px] px-4 py-2.5 text-sm {{ $child['active'] ? 'border-brand bg-white/[0.06] text-white' : 'border-transparent text-white/72 hover:bg-white/[0.06] hover:text-white' }}">
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                            @continue
                        @endif

                        <a href="{{ $item['url'] }}"
                           class="flex items-center gap-1.5 whitespace-nowrap border-l-[3px] py-2.5 pl-3 lg:border-l-0 lg:border-b-[3px] lg:py-1.5 lg:pl-0 {{ $item['active'] ? 'border-brand text-white' : 'border-transparent text-white/72 hover:text-white' }}">
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
                       class="order-2 flex shrink-0 items-center gap-2 rounded-[4px] border border-white/10 bg-white/[0.06] px-2.5 py-2.5 hover:bg-white/[0.12] lg:order-3 lg:py-1.5"
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

                @if ($adminUser)
                    {{-- De vuelta al panel de administración, desde cualquier
                         página pública. --}}
                    <a id="admin-panel" href="{{ url('/admin') }}"
                       class="order-2 flex shrink-0 items-center gap-2 rounded-[4px] border border-brand/40 bg-brand/10 px-2.5 py-2.5 text-brand hover:bg-brand/20 lg:order-3 lg:py-1.5"
                       title="Ir al panel de administración" aria-label="Panel de administración">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 4.3a1.7 1.7 0 0 1 3.4 0 1.7 1.7 0 0 0 2.5 1l.1-.1a1.7 1.7 0 0 1 2.4 2.4l-.1.1a1.7 1.7 0 0 0 1 2.5 1.7 1.7 0 0 1 0 3.4 1.7 1.7 0 0 0-1 2.5l.1.1a1.7 1.7 0 0 1-2.4 2.4l-.1-.1a1.7 1.7 0 0 0-2.5 1 1.7 1.7 0 0 1-3.4 0 1.7 1.7 0 0 0-2.5-1l-.1.1a1.7 1.7 0 0 1-2.4-2.4l.1-.1a1.7 1.7 0 0 0-1-2.5 1.7 1.7 0 0 1 0-3.4 1.7 1.7 0 0 0 1-2.5l-.1-.1A1.7 1.7 0 0 1 7.8 5.2l.1.1a1.7 1.7 0 0 0 2.5-1Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span class="hidden font-display text-xs font-semibold uppercase tracking-[0.08em] lg:block">Panel</span>
                    </a>
                @endif

                @if ($isGuest)
                    {{-- La puerta de entrada del técnico, ahora a la vista en la
                         cabecera: un icono, sin texto que ocupe sitio. --}}
                    <a id="coach-login" href="{{ route('filament.club.auth.login') }}"
                       class="order-2 flex size-10 shrink-0 items-center justify-center rounded-[4px] border border-white/10 bg-white/[0.06] text-white/80 hover:bg-white/[0.12] hover:text-white lg:order-3"
                       title="Acceso técnicos" aria-label="Acceso técnicos">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
                    </a>
                @endif
            </div>
        </header>

        {{-- Una copa internacional cambia todo el contenido de la página
             —fondo, colores, superficies— y deja la cabecera y el pie como
             están: ver App\Enums\CupDesign. --}}
        @php($theme = $theme ?? null)
        @if ($theme)
            <div class="cup-stage {{ $theme->stageClass() }} flex-1" style="{{ $theme->style() }}">
                <main class="mx-auto max-w-6xl px-6 py-10">
                    {{ $slot }}
                </main>
            </div>
        @else
            <main class="mx-auto w-full max-w-6xl flex-1 px-6 py-10">
                {{ $slot }}
            </main>
        @endif

        <footer class="{{ $theme ? '' : 'mt-10' }} bg-ink py-8 text-white/60">
            <div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-8 px-6">
                <div class="max-w-xs">
                    <div class="flex items-baseline gap-2 font-display text-xl">
                        <span class="font-extrabold text-brand">AMAZON</span>
                        <span class="font-semibold tracking-[0.14em] text-white">TOERNOOIEN</span>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed">Clasificación, partidos y goleadores de la liga.</p>
                </div>

                {{-- En el móvil, en rejilla de dos columnas: en fila envolvían sueltas
                     y el pie quedaba descuadrado. --}}
                <div class="grid w-full grid-cols-2 gap-x-8 gap-y-6 sm:flex sm:w-auto sm:flex-wrap sm:gap-10">
                    <div class="flex flex-col gap-2">
                        <span class="font-mono text-[9px] tracking-[0.14em] text-brand">COMPETICIÓN</span>
                        <a href="{{ route('site.home') }}" class="text-sm text-white/60 hover:text-brand">Inicio</a>
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

        <script>
            {{-- Al primer visitante, y sólo una vez: el navegador pregunta
                 por su cuenta (ver public/js/push.js), sin botón que lo pida. --}}
            {{-- El menú de Copas se cierra al tocar fuera, como cualquier
                 desplegable. --}}
            document.getElementById('site-menu-toggle')?.addEventListener('click', function () {
                var open = document.getElementById('site-nav').toggleAttribute('data-open');
                this.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            document.addEventListener('click', function (event) {
                document.querySelectorAll('details.site-nav-menu[open]').forEach(function (menu) {
                    if (! menu.contains(event.target)) {
                        menu.removeAttribute('open');
                    }
                });
            });

            document.addEventListener('DOMContentLoaded', function () {
                if (typeof window.LigaPush === 'undefined') {
                    return;
                }

                var vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
                var csrf = document.querySelector('meta[name="csrf-token"]')?.content;

                if (vapidKey) {
                    {{-- El chat vive aquí desde que salió de los dos paneles, y
                         el permiso para sus avisos se seguía pidiendo sólo allí
                         dentro: un técnico que entre por el sitio nunca llegaba
                         a suscribirse y no le avisaba nadie de un mensaje. En
                         fila y no a la vez, que el endpoint del navegador es
                         uno solo. --}}
                    window.LigaPush.autoPromptAll(
                        @if ($chatUser) ['matches', 'news', 'chat'] @else ['matches', 'news'] @endif,
                        vapidKey,
                        csrf,
                    );
                }
            });
        </script>
    </body>
</html>
