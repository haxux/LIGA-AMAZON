{{-- El palmarés entero, no el de la temporada elegida: un título se gana una
     vez y se exhibe siempre. Es para lo que existe la entidad Club.

     Como una vitrina: cada título una vez, con cuántas veces se ganó y en qué
     temporadas, del más ganado al menos. --}}
@php
    $trophyIcon = '<path d="M42 8h36v18a18 18 0 0 1-36 0z" /><path d="M42 13H30v5a13 13 0 0 0 13 13M78 13h12v5a13 13 0 0 1-13 13" /><path d="M60 44v12M46 70h28M50 70l2-14h16l2 14" />';
@endphp

@if ($trophies->isEmpty())
    <div class="flex flex-col items-center gap-3 rounded-md bg-surface px-4 py-10 text-center">
        <svg viewBox="0 0 120 80" class="h-20 text-white/15" fill="none" stroke="currentColor" stroke-width="3.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $trophyIcon !!}</svg>
        <p class="font-display text-lg uppercase tracking-wide text-white/60">Este club todavía no ha ganado ningún título.</p>
    </div>
@else
    @php
        $cabinet = $trophies->groupBy('name')
            ->map(fn ($won, $name) => ['name' => $name, 'won' => $won])
            ->sortByDesc(fn ($entry) => $entry['won']->count())
            ->values();
    @endphp

    <div class="mb-4 flex items-baseline gap-3 rounded-md bg-surface px-5 py-3">
        <span class="font-mono text-[10px] tracking-[0.12em] text-white/60">TÍTULOS</span>
        <span class="-skew-x-8 font-display text-3xl font-extrabold tabular-nums text-brand">{{ $trophies->count() }}</span>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cabinet as $entry)
            <div class="flex items-center gap-4 rounded-md bg-surface p-4">
                <span class="relative grid size-16 shrink-0 place-items-center rounded-[5px] bg-surface-alt text-brand">
                    <svg viewBox="0 0 120 80" class="h-9" fill="none" stroke="currentColor" stroke-width="5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $trophyIcon !!}</svg>
                    @if ($entry['won']->count() > 1)
                        <span class="absolute -right-1.5 -bottom-1.5 grid h-6 min-w-6 -skew-x-12 place-items-center rounded-[3px] bg-brand px-1 font-display text-sm font-bold text-ink"
                              title="Ganado {{ $entry['won']->count() }} veces"><span class="skew-x-12">×{{ $entry['won']->count() }}</span></span>
                    @endif
                </span>

                <span class="min-w-0">
                    <span class="block font-display text-xl font-bold uppercase italic leading-tight text-white">{{ $entry['name'] }}</span>
                    <span class="mt-1 flex flex-wrap gap-1">
                        @foreach ($entry['won'] as $trophy)
                            <span class="rounded-[2px] bg-white/10 px-1.5 py-px font-mono text-[10px] tracking-[0.08em] text-white/70">{{ $trophy->season?->name }}</span>
                        @endforeach
                    </span>
                </span>
            </div>
        @endforeach
    </div>
@endif
