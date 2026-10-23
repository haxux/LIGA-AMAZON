{{-- Las dos caras de Noticias: las de la liga, para todos, y «A Segunda», el
     foro interno, sólo para quien tiene sesión. Un visitante no ve las
     pestañas: para él Noticias es sólo lo de siempre. --}}
@props(['current'])

@if (app(App\Support\SiteAccount::class)->active())
    <nav class="mb-6 inline-flex gap-1.5 rounded-md bg-ink px-2 py-1.5" aria-label="Secciones de Noticias">
        @foreach (['news' => ['Noticias', route('site.news.index')], 'wall' => ['A Segunda', route('site.wall.index')]] as $key => [$label, $href])
            <a href="{{ $href }}" @if ($current === $key) aria-current="page" @endif
               @class([
                   '-skew-x-12 rounded-[3px] px-4 py-2 font-display text-base font-bold uppercase italic tracking-[0.06em] transition-colors',
                   'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand',
                   'bg-brand text-ink' => $current === $key,
                   'text-white/70 hover:bg-white/[0.06] hover:text-white' => $current !== $key,
               ])>
                <span class="inline-flex skew-x-12 items-center gap-2">
                    {{ $label }}
                    @if ($key === 'wall')
                        <span class="rounded-[2px] bg-black/20 px-1 font-mono text-[9px] not-italic tracking-[0.08em] {{ $current === $key ? 'text-ink/80' : 'text-white/60' }}">INTERNO</span>
                    @endif
                </span>
            </a>
        @endforeach
    </nav>
@endif
