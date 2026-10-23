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

        {{-- La web instalable como app (ver ManifestController): el manifest
             para Android y el ordenador, y lo que el iPhone lee por su cuenta
             para abrirla a pantalla completa con su nombre bajo el icono. --}}
        <link rel="manifest" href="{{ route('site.manifest') }}">
        <meta name="theme-color" content="{{ config('site.theme_color') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black">
        <meta name="apple-mobile-web-app-title" content="{{ config('site.short_name') }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Notificaciones push de partidos: ver public/js/push.js y
             App\Http\Controllers\PushSubscriptionController. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
        {{-- Qué cuentas hay abiertas (una huella, sin datos): si cambian, el
             navegador vuelve a apuntarse a los avisos del chat (push.js). --}}
        <meta name="push-accounts" content="{{ app(App\Support\SiteAccount::class)->fingerprint() }}">
        <x-push-script />
    </head>
    {{-- Columna a pantalla completa: el contenido crece (`flex-1`) y empuja el
         pie al fondo. Con una página corta —una clasificación con pocos
         equipos— el pie quedaba a media pantalla y debajo se veía el fondo
         amarillo vacío. --}}
    <body class="flex min-h-screen flex-col bg-brand-weave font-sans text-ink">
        {{-- Lo primero del <body>: la animación de entrada de la app instalada,
             que tiene que pintarse antes que la página. --}}
        <x-site.app-intro :season="app(App\Services\SeasonResolver::class)->active()?->name" />

        @php
            // El técnico se lee del guard `club`, el de su panel, que vive en la
            // misma sesión que el sitio: `auth()` a secas devuelve el guard del
            // administrador y aquí no diría nada.
            //
            // Con las dos cuentas abiertas, el sitio usa la ACTIVA, la que se
            // eligió en el selector de cuentas (ver SiteAccount): como técnico
            // ve «Mi equipo» y su escudo; como administrador, el botón de su
            // panel. Las dos cuentas abiertas salen en el selector.
            $siteAccount = app(App\Support\SiteAccount::class);
            $accounts = $siteAccount->accounts();
            $activeGuard = $siteAccount->activeGuard();
            $coach = $siteAccount->coach();
            $coachClub = $coach?->club;
            $coachSeason = app(App\Services\SeasonResolver::class)->active();

            // El administrador que mira el sitio público: un botón para volver
            // a su panel. Y quien no ha entrado de ninguna manera: un icono
            // para hacerlo, que antes estaba sólo en el pie.
            $adminUser = $siteAccount->admin();
            $isGuest = $accounts->isEmpty();

            // El chat es de técnicos Y presidentes: el de la cuenta activa.
            $chatUser = $siteAccount->active();
            $chatUnread = $chatUser ? App\Models\Conversation::unreadTotalFor($chatUser) : 0;

            $boundClub = request()->route('club');
            $boundClubId = is_object($boundClub) ? $boundClub->getKey() : $boundClub;

            // Estar en la ficha del club propio marca "Mi equipo" y no también
            // "Equipos": dos subrayados a la vez sólo emborronan dónde estás.
            $onOwnClub = $coachClub !== null
                && request()->routeIs('site.clubs.show')
                && (int) $boundClubId === (int) $coachClub->getKey();

            // Las divisiones de la temporada vigente que ya tienen equipos: lo que
            // el menú de Equipos ofrece.
            $navDivisions = $coachSeason
                ? App\Models\Division::query()->where('season_id', $coachSeason->getKey())->has('teams')->orderBy('id')->get()
                : collect();

            // Historia sólo se enlaza en el pie si hay algo detrás, igual que
            // el botón de Clasificación: con el archivo vacío sería una puerta
            // a una habitación vacía.
            $hasHistory = app(App\Services\SeasonResolver::class)->archived()->isNotEmpty();

            $nav = [
                // Historia cuelga de Clasificación, así que resalta con ella:
                // quien está mirando una tabla de 2025 sigue estando en la
                // sección de las tablas.
                ['label' => 'Clasificación', 'url' => route('site.standings'), 'active' => request()->routeIs('site.standings') || request()->routeIs('site.history')],
                ['label' => 'Partidos', 'url' => route('site.fixtures'), 'active' => request()->routeIs('site.fixtures')],
                ['label' => 'Estadísticas', 'url' => route('site.scorers'), 'active' => request()->routeIs('site.scorers')],
                // Equipos abre un menú, como Copas: una división por entrada, y
                // la lista de cada una gana el espacio de toda la página.
                ['label' => 'Equipos', 'url' => route('site.clubs.index'), 'active' => request()->routeIs('site.clubs.*') && ! $onOwnClub, 'children' => array_merge(
                    [['label' => 'Todas las divisiones', 'url' => route('site.clubs.index'), 'active' => request()->routeIs('site.clubs.index') && ! request()->filled('division')]],
                    $navDivisions->map(fn ($division) => [
                        'label' => $division->name,
                        'url' => route('site.clubs.index', ['division' => $division->getKey()]),
                        'active' => request()->routeIs('site.clubs.index') && (int) request('division') === (int) $division->getKey(),
                    ])->all(),
                )],
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
                <a href="{{ route('site.home') }}" class="order-1 mr-auto shrink-0 lg:mr-0" aria-label="Amazon Toernooien, inicio">
                    <x-site.wordmark size="text-[22px] lg:text-[22px] xl:text-[26px]" />
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

                <nav id="site-nav" class="order-4 mt-3 hidden w-full flex-col gap-y-0.5 border-t border-white/10 pt-2 font-display text-base font-semibold uppercase tracking-[0.08em] data-open:flex lg:tracking-[0.03em] xl:tracking-[0.05em] lg:order-2 lg:mt-0 lg:flex lg:w-auto lg:min-w-0 lg:flex-1 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-0.5 lg:gap-y-1 lg:border-0 lg:pt-0 lg:text-[15px] xl:gap-x-1">
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
                                <summary class="flex cursor-pointer list-none whitespace-nowrap rounded-[3px] px-3 py-2.5 italic -skew-x-12 transition-colors lg:px-2 lg:py-1.5 xl:px-3 [&::-webkit-details-marker]:hidden {{ $item['active'] ? 'bg-brand text-ink' : 'text-white/72 hover:bg-white/[0.06] hover:text-white' }}">
                                    <span class="flex skew-x-12 items-center gap-1">
                                        {{ $item['label'] }}
                                        <svg class="size-3 transition-transform group-open:rotate-180" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5"/></svg>
                                    </span>
                                </summary>

                                <div class="ml-3 border-l border-white/10 lg:absolute lg:left-0 lg:top-full lg:z-50 lg:ml-0 lg:mt-2 lg:min-w-[14rem] lg:overflow-hidden lg:rounded-[5px] lg:border lg:bg-ink lg:shadow-xl">
                                    @foreach ($item['children'] as $child)
                                        <a href="{{ $child['url'] }}"
                                           @if ($child['active']) aria-current="page" @endif
                                           class="flex items-center gap-2.5 px-4 py-2.5 text-sm {{ $child['active'] ? 'bg-white/[0.06] text-white' : 'text-white/72 hover:bg-white/[0.06] hover:text-white' }}">
                                            <span class="h-3 w-1.5 shrink-0 -skew-x-12 rounded-[1px] {{ $child['active'] ? 'bg-brand' : 'bg-white/15' }}" aria-hidden="true"></span>
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                            @continue
                        @endif

                        {{-- La página activa, con la placa amarilla inclinada de
                             las pestañas y los filtros del resto del sitio. --}}
                        <a href="{{ $item['url'] }}"
                           @if ($item['active']) aria-current="page" @endif
                           class="flex -skew-x-12 items-center whitespace-nowrap rounded-[3px] px-3 py-2.5 italic transition-colors lg:px-2 lg:py-1.5 xl:px-3 {{ $item['active'] ? 'bg-brand text-ink' : 'text-white/72 hover:bg-white/[0.06] hover:text-white' }}">
                            <span class="flex skew-x-12 items-center gap-1.5">
                            {{ $item['label'] }}

                            @if (($item['badge'] ?? 0) > 0)
                                {{-- Los no leídos, visibles desde cualquier
                                     página: es el aviso que el chat tiene, ya
                                     que no hay correos ni websockets. --}}
                                <span class="flex min-w-[1.15rem] items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold not-italic leading-[1.15rem] text-ink">
                                    {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                                </span>
                            @endif
                            </span>
                        </a>
                    @endforeach
                </nav>

                {{-- Con sesión, el selector de cuentas: la que se está usando,
                     cambiar a la otra si hay dos, y entrar o salir (ver
                     site.account-menu). --}}
                @if (! $isGuest)
                    <x-site.account-menu :accounts="$accounts" :active-guard="$activeGuard" :coach-club="$coachClub" />
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

        <footer class="{{ $theme ? '' : 'mt-10' }} bg-ink text-white/60">
            <div class="mx-auto flex max-w-6xl flex-wrap justify-between gap-8 px-6 py-8">
                <div class="max-w-xs">
                    <x-site.wordmark size="text-xl" />
                    <p class="mt-3 text-sm leading-relaxed">La liga de un vistazo: clasificación, partidos, equipos y noticias.</p>
                </div>

                {{-- En el móvil, en rejilla de dos columnas: en fila envolvían sueltas
                     y el pie quedaba descuadrado. --}}
                <div class="grid w-full grid-cols-2 gap-x-8 gap-y-6 sm:flex sm:w-auto sm:flex-wrap sm:gap-12">
                    @foreach ([
                        'Competición' => [
                            ['Inicio', route('site.home')],
                            ['Clasificación', route('site.standings')],
                            ['Partidos', route('site.fixtures')],
                            ['Copas', route('site.cups.index')],
                            ...($hasHistory ? [['Historia', route('site.history')]] : []),
                        ],
                        'Clubes' => [
                            ['Equipos', route('site.clubs.index')],
                            ['Estadísticas', route('site.scorers')],
                        ],
                        // La puerta de entrada del técnico. Discreta a propósito:
                        // el visitante no tiene nada que hacer aquí.
                        'Sitio' => [
                            ['Noticias', route('site.news.index')],
                            ['Acceso técnicos', route('filament.club.auth.login')],
                        ],
                    ] as $column => $links)
                        <div class="flex flex-col gap-2">
                            <span class="mb-1 flex items-center gap-2 font-display text-base font-bold uppercase italic tracking-wide text-white">
                                <span class="h-3.5 w-1.5 shrink-0 -skew-x-12 rounded-[1px] bg-brand" aria-hidden="true"></span>
                                {{ $column }}
                            </span>
                            @foreach ($links as [$text, $href])
                                <a href="{{ $href }}" class="text-sm text-white/60 hover:text-brand">{{ $text }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-white/10">
                <div class="mx-auto max-w-6xl px-6 py-4 font-mono text-[10px] tracking-[0.08em] text-white/60">
                    &copy; {{ now()->year }} AMAZON TOERNOOIEN
                    @if ($coachSeason) · {{ mb_strtoupper($coachSeason->name) }} @endif
                </div>
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

                    {{-- Sin ninguna cuenta abierta, este navegador deja de
                         recibir el chat: un teléfono prestado o compartido no
                         sigue avisando a quien ya cerró su sesión. --}}
                    @if (! $chatUser)
                        window.LigaPush.release('chat', csrf);
                    @endif
                }
            });
        </script>
    </body>
</html>
