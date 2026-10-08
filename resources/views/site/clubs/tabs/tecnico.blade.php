{{-- El nombre visible del técnico es el de su cuenta: decisión cerrada con el
     propietario en la Fase 9. Ni el correo ni nada más se publica. --}}
@if ($coach)
    <div class="flex items-center gap-4 rounded-[6px] bg-surface-alt p-5">
        @if ($coach->photo_path)
            <img src="{{ Storage::disk(config('filesystems.uploads'))->url($coach->photo_path) }}" alt=""
                 class="size-12 shrink-0 rounded-full object-cover">
        @else
            <span class="size-12 shrink-0 rounded-full bg-surface-muted" aria-hidden="true"></span>
        @endif

        <div>
            <span class="block font-mono text-[9px] tracking-[0.14em] text-brand">DIRECTOR TÉCNICO</span>
            <span class="font-display text-2xl uppercase tracking-wide text-white">{{ $coach->name }}</span>
        </div>
    </div>
@else
    <p class="rounded-md bg-surface px-4 py-6 text-center font-display text-lg uppercase tracking-wide text-white/60">
        Este club no tiene director técnico asignado.
    </p>
@endif
