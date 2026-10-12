@props(['ficha', 'esPortero' => false])

@php
    // EA REUTILIZA las seis casillas en los porteros, y no significan lo
    // mismo: lo que en un delantero es velocidad, en un portero es estirada.
    // Se comprobó con datos reales — Courtois sale 87/89/78/90/46/90, y ese 46
    // es su velocidad, no su defensa. Enseñarlas con las etiquetas de campo
    // sería mentir con seis números a la vez.
    $casillas = $esPortero
        ? ['DIV' => 'pace', 'PAR' => 'shooting', 'SAQ' => 'passing', 'REF' => 'dribbling', 'VEL' => 'defending', 'COL' => 'physicality']
        : ['RIT' => 'pace', 'TIR' => 'shooting', 'PAS' => 'passing', 'REG' => 'dribbling', 'DEF' => 'defending', 'FÍS' => 'physicality'];

    $color = fn (?int $v) => match (true) {
        $v === null => 'text-white/30',
        $v >= 80 => 'text-emerald-400',
        $v >= 70 => 'text-lime-400',
        $v >= 60 => 'text-amber-400',
        default => 'text-rose-400',
    };

    $pie = ['Right' => 'Diestro', 'Left' => 'Zurdo'][$ficha->preferred_foot] ?? $ficha->preferred_foot;
@endphp

<section class="mb-6">
    <h2 class="mb-3 font-mono text-[10px] tracking-[0.14em] text-brand">
        VALORACIÓN {{ mb_strtoupper($ficha->edition ?? 'EA FC') }}
    </h2>

    <div class="rounded-[6px] bg-surface-alt p-5">
        <div class="flex flex-wrap items-center gap-6">
            {{-- La media, que es la cifra que todo el mundo busca primero. --}}
            <div class="text-center">
                <span class="block font-display text-5xl font-bold {{ $color($ficha->overall_rating) }}">
                    {{ $ficha->overall_rating ?? '—' }}
                </span>
                <span class="font-mono text-[9px] tracking-[0.14em] text-white/40">MEDIA</span>
            </div>

            <div class="grid flex-1 grid-cols-3 gap-x-4 gap-y-2 sm:grid-cols-6">
                @foreach ($casillas as $etiqueta => $campo)
                    <div class="text-center">
                        <span class="block font-display text-2xl font-bold {{ $color($ficha->$campo) }}">
                            {{ $ficha->$campo ?? '—' }}
                        </span>
                        <span class="font-mono text-[9px] tracking-[0.12em] text-white/40">{{ $etiqueta }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-white/[0.07] pt-3 font-mono text-[10px] tracking-[0.1em] text-white/50">
            @if ($pie) <span>PIE: {{ mb_strtoupper($pie) }}</span> @endif
            @if ($ficha->weak_foot) <span>PIE MALO: {{ str_repeat('★', $ficha->weak_foot).str_repeat('☆', 5 - $ficha->weak_foot) }}</span> @endif
            @if ($ficha->skill_moves) <span>FILIGRANAS: {{ str_repeat('★', $ficha->skill_moves).str_repeat('☆', 5 - $ficha->skill_moves) }}</span> @endif
            @if ($ficha->nationality) <span>{{ mb_strtoupper($ficha->nationality) }}</span> @endif
            @if ($ficha->club) <span>EN EA: {{ mb_strtoupper($ficha->club) }}</span> @endif
        </div>
    </div>
</section>
