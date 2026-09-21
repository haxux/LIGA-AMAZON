{{-- El palmarés entero, no el de la temporada elegida: un título se gana una
     vez y se exhibe siempre. Es para lo que existe la entidad Club. --}}
@if ($trophies->isEmpty())
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        Este club todavía no ha ganado ningún título.
    </p>
@else
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($trophies as $trophy)
            <div class="flex items-center gap-3 rounded-[5px] border-l-[3px] border-brand bg-surface-alt p-4">
                <span class="font-display text-2xl text-brand" aria-hidden="true">&#9819;</span>
                <span class="min-w-0">
                    <span class="block truncate font-display text-lg font-semibold text-white">{{ $trophy->name }}</span>
                    <span class="block font-mono text-[10px] tracking-[0.12em] text-white/45">{{ $trophy->season?->name }}</span>
                </span>
            </div>
        @endforeach
    </div>
@endif
