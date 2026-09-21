@php
    // Las etiquetas viven aquí y no en el modelo: el vocabulario que `Player`
    // guarda está en inglés desde la Fase 2 y el sitio público es español.
    $labels = [
        'Goalkeeper' => 'Porteros',
        'Defender' => 'Defensas',
        'Midfielder' => 'Centrocampistas',
        'Forward' => 'Delanteros',
    ];
@endphp

@php($total = collect($squad)->flatMap(fn ($group) => $group['members'])->sum(fn ($membership) => $membership->player?->market_value ?? 0))

@if ($total > 0)
    {{-- Entero con separador de miles y sin símbolo de moneda: la liga no fija
         una divisa (decisión de la Fase 12). --}}
    <div class="mb-6 flex items-baseline gap-3 rounded-[5px] bg-surface-alt px-4 py-3">
        <span class="font-mono text-[10px] tracking-[0.14em] text-white/45">VALOR DE LA PLANTILLA</span>
        <span class="font-display text-2xl font-bold text-brand">{{ number_format($total, 0, ',', '.') }}</span>
    </div>
@endif

@forelse ($squad as $group)
    <section class="mb-8">
        <h2 class="font-display mb-3 rounded-[4px] bg-surface-alt px-4 py-2 text-lg uppercase tracking-wide text-brand">
            {{ $labels[$group['position']] ?? $group['position'] }}
        </h2>

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($group['members'] as $membership)
                <div class="flex items-center gap-3 rounded-[5px] bg-surface-alt p-3">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-[3px] bg-surface-muted font-display text-sm font-bold text-brand">
                        {{ $membership->shirt_number ?? '—' }}
                    </span>

                    <span class="min-w-0">
                        <span class="block truncate font-display text-base font-semibold text-white">
                            {{ $membership->player?->name }}
                        </span>
                        <span class="block font-mono text-[10px] tracking-[0.1em] text-white/45">
                            {{ $membership->player?->specific_position ?? '—' }}
                            @if ($membership->player?->birth_date) · {{ $membership->player->birth_date->age }} AÑOS @endif
                            @if ($membership->player?->market_value) · {{ number_format($membership->player->market_value, 0, ',', '.') }} @endif
                            @if ($membership->type === \App\Models\SquadMembership::TYPE_LOAN) · CEDIDO @endif
                        </span>
                    </span>
                </div>
            @endforeach
        </div>
    </section>
@empty
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        Sin plantilla inscrita en esta temporada.
    </p>
@endforelse
