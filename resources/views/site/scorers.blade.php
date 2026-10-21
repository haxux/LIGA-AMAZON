<x-layouts.site :title="'Estadísticas — Liga Amazon'">
    <h1 class="font-display mb-1 -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">Estadísticas</h1>
    {{-- La temporada va delante: es el marco de todo lo demás, y sin ella en el
         subtítulo una cifra de un año cerrado se confunde con la de ahora. --}}
    <div class="mb-6 font-mono text-[10px] tracking-[0.14em] text-ink/70">
        {{ mb_strtoupper($selectedSeason->name) }}
        ·
        {{ mb_strtoupper($competition->isAll() ? 'Todas las competiciones' : $competition->label) }}
    </div>

    @php
        // La URL de la página con un filtro cambiado y los demás como están.
        // Lo que ya no encaje (una jornada que esa división no tiene) lo deja
        // el controlador en «todas», igual que con el formulario de antes.
        $current = [
            'temporada' => $selectedSeason->id,
            'competicion' => $competition->key,
            'division' => $selectedDivision,
            'fecha' => $selectedFecha,
        ];
        $filterUrl = fn (string $name) => fn ($value) => route('site.scorers', array_filter(
            array_merge($current, [$name => $value]),
            fn ($v) => $v !== null && $v !== '',
        ));
    @endphp

    {{-- Los filtros van de ancho a estrecho, y los tres últimos se derivan de
         la temporada: al cambiarla, una copa o una división de la anterior ya
         no existe y el controlador la deja en «todas» sola.

         La temporada sigue siendo un desplegable porque la lista crece cada
         año; el resto tiene pocas opciones y van a la vista. La competición
         sólo se ofrece si hay copas (sin ellas «Todo» y «Liga» son la misma
         lista). División y jornada son de la liga: al elegirlas las copas
         quedan fuera de las cifras. --}}
    <div class="mb-8 grid gap-3 rounded-md border-t-4 border-brand bg-surface p-4">
        <form method="GET" action="{{ route('site.scorers') }}"
              class="grid gap-1.5 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:items-center sm:gap-4">
            <label for="filtro-temporada" class="font-mono text-[10px] tracking-[0.12em] text-white/60">TEMPORADA</label>
            <div class="flex gap-2">
                <select id="filtro-temporada" name="temporada" onchange="this.form.submit()"
                        class="w-full rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white focus-visible:outline-2 focus-visible:outline-brand sm:max-w-[260px]">
                    @foreach ($seasons->pluck('name', 'id') as $value => $text)
                        <option value="{{ $value }}" @selected((string) $value === (string) $selectedSeason->id)>{{ $text }}</option>
                    @endforeach
                </select>
                @foreach (['competicion', 'division', 'fecha'] as $kept)
                    @if (filled($current[$kept]))
                        <input type="hidden" name="{{ $kept }}" value="{{ $current[$kept] }}">
                    @endif
                @endforeach
                <noscript>
                    <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
                </noscript>
            </div>
        </form>

        @if (count($competitionOptions) > 2)
            <x-site.stat-segments label="COMPETICIÓN" :options="$competitionOptions"
                                  :selected="$competition->key" :url="$filterUrl('competicion')" />
        @endif

        @if (count($divisionOptions) > 2)
            <x-site.stat-segments label="DIVISIÓN" :options="$divisionOptions"
                                  :selected="$selectedDivision ?? ''" :url="$filterUrl('division')" />
        @endif

        @if (count($fechaOptions) > 1)
            <x-site.stat-segments label="JORNADA" :options="$fechaOptions" ruler
                                  :selected="$selectedFecha ?? ''" :url="$filterUrl('fecha')" />
        @endif
    </div>

    @php
        $boards = [
            // Un color por tabla, como los rótulos de una retransmisión. Goles,
            // asistencias y porterías van en matices del amarillo de la liga
            // (decisión del propietario: los tonos de la página); los dos MVP,
            // que son premios y no cifras de juego, llevan su propio color.
            ['panel' => 'goles', 'tab' => 'Goles', 'title' => 'Máximos goleadores', 'rows' => $scorers, 'accent' => '#FFB800'],
            ['panel' => 'asistencias', 'tab' => 'Asistencias', 'title' => 'Máximos asistentes', 'rows' => $assisters, 'accent' => '#E89E00'],
            ['panel' => 'porterias', 'tab' => 'Porterías', 'title' => 'Máximas porterías a cero', 'rows' => $cleanSheets, 'accent' => '#FFD666'],
            ['panel' => 'mvp', 'tab' => 'MVP', 'title' => 'MVP del partido', 'rows' => $mvps, 'accent' => '#FF5C7A'],
            ['panel' => 'mvp-semana', 'tab' => 'MVP semana', 'title' => 'Week MVP', 'rows' => $weekMvps, 'accent' => '#B18CFF'],
        ];
    @endphp

    {{-- En el móvil, una tabla a la vez con pestañas: las cinco seguidas eran
         metros de scroll. Sin JavaScript las pestañas no aparecen y se ven
         todas, como antes. En pantalla ancha, los goles —lo que más se mira—
         ocupan dos columnas. --}}
    <div data-stat-tabs>
        <div class="mb-4 hidden gap-1.5 overflow-x-auto rounded-[6px] bg-ink px-2 py-1.5 md:hidden" role="tablist" data-stat-tablist>
            @foreach ($boards as $board)
                <button type="button" role="tab" aria-controls="{{ $board['panel'] }}" data-stat-tab="{{ $board['panel'] }}"
                        style="--accent: {{ $board['accent'] }}"
                        class="shrink-0 -skew-x-12 rounded-[3px] border-b-2 border-(--accent) px-3 py-2 font-display text-sm font-bold uppercase italic tracking-[0.06em] text-white/70 aria-selected:bg-(--accent) aria-selected:text-ink focus-visible:outline-2 focus-visible:outline-(--accent)">
                    <span class="inline-block skew-x-12">{{ $board['tab'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="grid items-start gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($boards as $board)
                <x-site.scorer-list :title="$board['title']" :rows="$board['rows']" :panel="$board['panel']"
                                    :accent="$board['accent']" :icon="$board['panel']"
                                    :class="$loop->first ? 'md:col-span-2' : ''" />
            @endforeach
        </div>
    </div>

    <script>
        (function () {
            // La jornada elegida, a la vista en la regla aunque sea la 30.
            const active = document.querySelector('[data-stat-ruler] [aria-current]');
            if (active) {
                const ruler = active.parentElement;
                ruler.scrollLeft = active.offsetLeft - (ruler.clientWidth - active.offsetWidth) / 2;
            }

            const root = document.querySelector('[data-stat-tabs]');
            const tabs = [...root.querySelectorAll('[data-stat-tab]')];
            const panels = [...root.querySelectorAll('[data-stat-panel]')];
            const key = 'estadisticas-pestana';

            // La pestaña sobrevive a cambiar de filtro: quien mira los MVP y
            // cambia de jornada quiere seguir viendo los MVP.
            let saved = null;
            try { saved = sessionStorage.getItem(key); } catch (e) {}

            function show(name) {
                tabs.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.statTab === name)));
                panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.statPanel === name));
                try { sessionStorage.setItem(key, name); } catch (e) {}
            }

            tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.statTab)));
            root.querySelector('[data-stat-tablist]').classList.replace('hidden', 'flex');
            root.classList.add('is-tabbed');
            show(tabs.some((tab) => tab.dataset.statTab === saved) ? saved : tabs[0].dataset.statTab);
        })();
    </script>
</x-layouts.site>
