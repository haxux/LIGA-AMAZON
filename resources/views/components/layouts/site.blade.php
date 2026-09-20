<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'Liga Amazon') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-brand-weave font-sans text-ink">
        @php
            $nav = [
                ['label' => 'Clasificación', 'route' => 'site.standings', 'pattern' => 'site.standings'],
                ['label' => 'Partidos', 'route' => 'site.fixtures', 'pattern' => 'site.fixtures'],
                ['label' => 'Goleadores', 'route' => 'site.scorers', 'pattern' => 'site.scorers'],
                ['label' => 'Noticias', 'route' => 'site.news.index', 'pattern' => 'site.news.*'],
            ];
        @endphp

        <header class="sticky top-0 z-40 bg-ink text-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-8 px-6 py-3">
                <a href="{{ route('site.standings') }}" class="flex items-baseline gap-2 font-display text-[27px] leading-none">
                    <span class="font-extrabold text-brand">AMAZON</span>
                    <span class="font-semibold tracking-[0.14em] text-white">TOERNOOIEN</span>
                </a>

                <nav class="flex flex-wrap items-center gap-7 font-display text-base font-semibold uppercase tracking-[0.12em]">
                    @foreach ($nav as $item)
                        <a href="{{ route($item['route']) }}"
                           class="border-b-[3px] py-1.5 {{ request()->routeIs($item['pattern']) ? 'border-brand text-white' : 'border-transparent text-white/72' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
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
                        <a href="{{ route('site.scorers') }}" class="text-sm text-white/60 hover:text-brand">Goleadores</a>
                    </div>
                    <div class="flex flex-col gap-2">
                        <span class="font-mono text-[9px] tracking-[0.14em] text-brand">SITIO</span>
                        <a href="{{ route('site.news.index') }}" class="text-sm text-white/60 hover:text-brand">Noticias</a>
                    </div>
                </div>
            </div>

            <div class="mx-auto mt-6 max-w-6xl border-t border-white/10 px-6 pt-4 font-mono text-[10px] tracking-[0.08em] text-white/40">
                &copy; {{ now()->year }} AMAZON TOERNOOIEN
            </div>
        </footer>
    </body>
</html>
