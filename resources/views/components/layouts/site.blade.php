<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-ink text-white">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'Liga Amazon') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-ink font-sans text-white">
        <header class="bg-ink border-b border-surface">
            <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4 font-display uppercase tracking-wide">
                <a href="{{ route('site.standings') }}" class="text-brand text-xl font-bold">Liga Amazon</a>

                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('site.standings') }}" class="hover:text-brand">Clasificación</a>
                    <a href="{{ route('site.fixtures') }}" class="hover:text-brand">Partidos</a>
                    @if (Route::has('site.scorers'))
                        <a href="{{ route('site.scorers') }}" class="hover:text-brand">Goleadores</a>
                    @endif
                    @if (Route::has('site.news.index'))
                        <a href="{{ route('site.news.index') }}" class="hover:text-brand">Noticias</a>
                    @endif
                </div>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-10">
            {{ $slot }}
        </main>

        <footer class="border-t border-surface py-6 text-center text-sm text-white/60">
            &copy; {{ now()->year }} Liga Amazon
        </footer>
    </body>
</html>
