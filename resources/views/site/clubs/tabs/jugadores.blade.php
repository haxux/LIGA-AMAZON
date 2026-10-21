@php
    // Las etiquetas viven aquí y no en el modelo: el vocabulario que `Player`
    // guarda está en inglés desde la Fase 2 y el sitio público es español.
    $labels = [
        'Goalkeeper' => 'Porteros',
        'Defender' => 'Defensas',
        'Midfielder' => 'Centrocampistas',
        'Forward' => 'Delanteros',
    ];

    $everyone = collect($squad)->flatMap(fn ($group) => $group['members']);
    $total = $everyone->sum(fn ($membership) => $membership->player?->market_value ?? 0);
    $ages = $everyone->map(fn ($membership) => $membership->player?->birth_date?->age)->filter();

    // El medidor de cada jugador se mide contra el más caro de la plantilla,
    // como el de Estadísticas contra el líder.
    $maxValue = max(1, (int) $everyone->max(fn ($membership) => $membership->player?->market_value ?? 0));

    // Entero con separador de miles y sin símbolo de moneda: la liga no fija
    // una divisa (decisión de la Fase 12).
    $money = fn (int $value) => number_format($value, 0, ',', '.');
@endphp

@if ($everyone->isNotEmpty())
    {{-- El resumen de la plantilla, con el valor total delante. --}}
    <dl class="mb-6 grid grid-cols-3 divide-x divide-white/[0.06] overflow-hidden rounded-md bg-surface">
        @foreach ([
            ['VALOR DE LA PLANTILLA', $total > 0 ? $money($total) : '—', true],
            ['JUGADORES', $everyone->count(), false],
            ['EDAD MEDIA', $ages->isNotEmpty() ? number_format($ages->avg(), 1, ',', '') : '—', false],
        ] as [$label, $value, $lead])
            <div class="px-4 py-3 sm:px-5">
                <dt class="font-mono text-[9px] tracking-[0.12em] text-white/60 sm:text-[10px]">{{ $label }}</dt>
                <dd class="mt-0.5 -skew-x-8 truncate font-display text-xl font-extrabold leading-none tabular-nums sm:text-3xl {{ $lead ? 'text-brand' : 'text-white' }}">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
@endif

@forelse ($squad as $group)
    <section class="mb-6 rounded-md bg-surface p-4">
        <x-site.panel-title>{{ $labels[$group['position']] ?? $group['position'] }}</x-site.panel-title>

        {{-- Una fila por jugador: dorsal grande, nombre, su posición como
             chapa, la edad y el valor con su medidor. En pantalla ancha, dos
             columnas. --}}
        <div class="grid gap-x-6 lg:grid-cols-2">
            @foreach ($group['members'] as $membership)
                @php($player = $membership->player)
                @php($value = (int) ($player?->market_value ?? 0))
                <a href="{{ route('site.players.show', array_filter(['player' => $membership->player_id, 'temporada' => $selectedSeason?->id])) }}"
                   class="flex items-center gap-3 border-b border-white/[0.05] py-2.5 transition-colors hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-brand {{ $membership->hasLeft() ? 'opacity-60' : '' }}">
                    <span class="w-9 shrink-0 -skew-x-8 text-center font-display text-2xl font-extrabold tabular-nums text-brand">{{ $membership->shirt_number ?? '—' }}</span>

                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="truncate font-display text-base font-bold text-white">{{ $player?->name }}</span>
                            @if ($membership->is_captain)
                                <span class="grid size-[18px] shrink-0 -skew-x-12 place-items-center rounded-[2px] bg-brand font-display text-[11px] font-bold text-ink" title="Capitán"><span class="skew-x-12">C</span></span>
                            @endif
                        </span>
                        <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 font-mono text-[10px] tracking-[0.08em] text-white/60">
                            <span class="-skew-x-12 rounded-[2px] bg-white/10 px-1.5 py-px font-bold text-white/80"><span class="inline-block skew-x-12">{{ $player?->specific_position ?? '—' }}</span></span>
                            @if ($player?->birth_date) <span>{{ $player->birth_date->age }} AÑOS</span> @endif
                            @if ($membership->type === \App\Models\SquadMembership::TYPE_LOAN) <span class="text-brand">CEDIDO</span> @endif
                            {{-- En una temporada cerrada salen también los que
                                 se fueron a mitad de año: sin decirlo parecería
                                 que acabaron el año aquí. --}}
                            @if ($membership->hasLeft()) <span>SALIÓ EL {{ $membership->left_at->format('d/m/Y') }}</span> @endif
                        </span>
                    </span>

                    @if ($value > 0)
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            <span class="font-display text-sm font-bold tabular-nums text-white">{{ $money($value) }}</span>
                            @php($filled = (int) max(1, round($value / $maxValue * 10)))
                            <span class="flex gap-0.5" aria-hidden="true">
                                @for ($i = 1; $i <= 10; $i++)
                                    <span @class(['h-2 w-1 -skew-x-12 rounded-[1px]', $i <= $filled ? 'bg-brand' : 'bg-white/10'])></span>
                                @endfor
                            </span>
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@empty
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        Sin plantilla inscrita en esta temporada.
    </p>
@endforelse
