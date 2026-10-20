{{-- El nombre visible del técnico es el de su cuenta: decisión cerrada con el
     propietario en la Fase 9. Ni el correo ni nada más se publica. --}}
@if ($coach)
    <div class="flex flex-wrap items-center gap-5 rounded-md bg-surface p-5 sm:p-6">
        @if ($coach->photo_path)
            <img src="{{ Storage::disk(config('filesystems.uploads'))->url($coach->photo_path) }}" alt=""
                 class="size-24 shrink-0 rounded-full object-cover ring-2 ring-brand">
        @else
            <span class="grid size-24 shrink-0 place-items-center rounded-full bg-surface-muted font-display text-3xl font-bold text-white/55 ring-2 ring-brand" aria-hidden="true">
                {{ collect(preg_split('/\s+/u', trim($coach->name)))->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('') }}
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <span class="inline-grid -skew-x-12 rounded-[3px] bg-brand px-2.5 py-1"><span class="skew-x-12 font-mono text-[10px] font-bold tracking-[0.14em] text-ink">DIRECTOR TÉCNICO</span></span>
            <span class="mt-2 block -skew-x-6 font-display text-3xl font-extrabold uppercase italic leading-none tracking-wide text-white sm:text-4xl">{{ $coach->name }}</span>
        </div>

        <x-site.club-crest :club="$club" size="size-14" />
    </div>
@else
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        Este club no tiene director técnico asignado.
    </p>
@endif
