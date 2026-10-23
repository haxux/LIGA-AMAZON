{{-- El formulario para publicar (o responder, con `parent`) en «A Segunda».

     Las fotos se reducen en el navegador antes de subirlas (a 1600 px, en
     JPEG): una foto de móvil pasa de varios MB a unos cientos de KB y sube
     rápido con datos. El servidor las vuelve a procesar de todas formas. --}}
@props(['parent' => null, 'placeholder' => '¿Qué se cuenta en la liga?'])

<form method="POST" action="{{ route('site.wall.store') }}" enctype="multipart/form-data" data-wall-composer
      {{ $attributes->merge(['class' => 'rounded-md border-t-4 border-brand bg-surface p-4']) }}>
    @csrf
    @if ($parent)
        <input type="hidden" name="parent_id" value="{{ $parent->getKey() }}">
    @endif

    <label for="wall-body-{{ $parent?->getKey() ?? 'nuevo' }}" class="sr-only">{{ $parent ? 'Tu respuesta' : 'Tu publicación' }}</label>
    <textarea id="wall-body-{{ $parent?->getKey() ?? 'nuevo' }}" name="body" rows="{{ $parent ? 2 : 3 }}" maxlength="{{ App\Models\WallPost::MAX_BODY }}"
              placeholder="{{ $placeholder }}"
              class="block w-full resize-y rounded-[6px] border border-white/10 bg-surface-alt px-3 py-2.5 text-[15px] text-white placeholder:text-white/40 focus-visible:outline-2 focus-visible:outline-brand">{{ old('parent_id') == $parent?->getKey() ? old('body') : '' }}</textarea>

    <div data-previews class="mt-2 hidden grid-cols-4 gap-1.5"></div>

    @if ($errors->any() && old('parent_id') == $parent?->getKey())
        <ul class="mt-2 space-y-0.5 text-sm text-rose-300">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mt-3 flex items-center gap-3">
        <label class="flex cursor-pointer items-center gap-2 rounded-[4px] px-2 py-1.5 font-mono text-[10px] tracking-[0.1em] text-white/70 hover:bg-white/[0.06] hover:text-white focus-within:outline-2 focus-within:outline-brand">
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="10" r="1.8" /><path d="m4 18 5-5 4 4 3-3 4 4" /></svg>
            FOTOS
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-wall-files>
        </label>
        <span class="font-mono text-[10px] tracking-[0.08em] text-white/50">HASTA {{ App\Models\WallPost::MAX_IMAGES }}</span>

        <span data-counter class="ml-auto font-mono text-[10px] tabular-nums text-white/50">0/{{ App\Models\WallPost::MAX_BODY }}</span>
        <button type="submit"
                class="-skew-x-12 rounded-[3px] bg-brand px-5 py-2 font-display text-base font-bold uppercase italic tracking-[0.06em] text-ink transition-colors hover:bg-brand/90 disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            <span class="inline-block skew-x-12">{{ $parent ? 'Responder' : 'Publicar' }}</span>
        </button>
    </div>
</form>
