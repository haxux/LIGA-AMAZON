{{-- El selector de cuentas de la cabecera (decisión del propietario,
     2026-10-23): las cuentas abiertas en este navegador —la de técnico y la de
     administrador—, cuál usa el sitio ahora y cambiar a la otra con un toque,
     sin volver a escribir la contraseña (ver SiteAccount y
     AccountSwitchController). También entrar con la que falta y salir de la
     activa sin tirar la otra.

     El botón es el de siempre: el escudo del club con la cuenta de técnico
     activa, el engranaje del panel con la de administrador. Un <details> como
     los menús de Equipos y Copas: se abre con un toque y sin JavaScript. --}}
@props(['accounts', 'activeGuard', 'coachClub' => null])

@php
    $active = $accounts->get($activeGuard);
    $missing = collect(App\Support\SiteAccount::GUARDS)->keys()->reject(fn ($guard) => $accounts->has($guard));
    $gear = '<path d="M10.3 4.3a1.7 1.7 0 0 1 3.4 0 1.7 1.7 0 0 0 2.5 1l.1-.1a1.7 1.7 0 0 1 2.4 2.4l-.1.1a1.7 1.7 0 0 0 1 2.5 1.7 1.7 0 0 1 0 3.4 1.7 1.7 0 0 0-1 2.5l.1.1a1.7 1.7 0 0 1-2.4 2.4l-.1-.1a1.7 1.7 0 0 0-2.5 1 1.7 1.7 0 0 1-3.4 0 1.7 1.7 0 0 0-2.5-1l-.1.1a1.7 1.7 0 0 1-2.4-2.4l.1-.1a1.7 1.7 0 0 0-1-2.5 1.7 1.7 0 0 1 0-3.4 1.7 1.7 0 0 0 1-2.5l-.1-.1A1.7 1.7 0 0 1 7.8 5.2l.1.1a1.7 1.7 0 0 0 2.5-1Z"/><circle cx="12" cy="12" r="3"/>';
    $describe = fn (string $guard, $user) => $guard === 'club'
        ? 'TÉCNICO · '.mb_strtoupper($user->club?->short_name ?: ($user->club?->name ?? ''))
        : 'ADMINISTRACIÓN';
@endphp

<details {{ $attributes->merge(['class' => 'site-nav-menu group relative order-2 shrink-0 lg:order-3']) }}>
    <summary @if ($activeGuard === 'club') id="coach-club" @else id="admin-panel" @endif
             @class([
                 'flex cursor-pointer list-none items-center gap-2 rounded-[4px] border px-2.5 py-2.5 lg:py-1.5 [&::-webkit-details-marker]:hidden',
                 'border-white/10 bg-white/[0.06] hover:bg-white/[0.12]' => $activeGuard === 'club',
                 'border-brand/40 bg-brand/10 text-brand hover:bg-brand/20' => $activeGuard !== 'club',
             ])
             title="Cuentas · {{ $activeGuard === 'club' ? ($coachClub?->name ?? $active?->name) : 'Administración' }}"
             aria-label="Cuentas: usando {{ $active?->name }}">
        @if ($activeGuard === 'club' && $coachClub)
            {{-- Sólo el escudo, que ya identifica al club: con el nombre al
                 lado, los enlaces del técnico no cabían en una línea. El nombre
                 está en el `title` y dentro del selector. --}}
            <x-site.club-crest :club="$coachClub" size="size-[20px]" />
        @else
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $gear !!}</svg>
            <span class="hidden font-display text-xs font-semibold uppercase tracking-[0.08em] xl:block">Panel</span>
        @endif
        {{-- Dos cuentas abiertas: una marca para que se sepa que aquí se cambia. --}}
        @if ($accounts->count() > 1)
            <span class="grid size-4 place-items-center rounded-full bg-brand font-display text-[10px] font-bold text-ink" aria-hidden="true">2</span>
        @endif
        <svg class="size-3 text-white/70 transition-transform group-open:rotate-180" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2.5 4.5 6 8l3.5-3.5"/></svg>
    </summary>

    <div class="absolute right-0 top-full z-50 mt-2 w-72 overflow-hidden rounded-md border border-white/10 bg-ink text-white shadow-xl">
        <p class="px-4 pt-3 pb-2 font-mono text-[10px] tracking-[0.12em] text-white/60">CUENTAS EN ESTE DISPOSITIVO</p>

        @foreach ($accounts as $guard => $user)
            @php($inUse = $guard === $activeGuard)
            <div @class(['flex items-center gap-3 px-4 py-3', 'bg-white/[0.06]' => $inUse])>
                @if ($guard === 'club' && $user->club)
                    <x-site.club-crest :club="$user->club" size="size-8" />
                @else
                    <span class="grid size-8 shrink-0 place-items-center rounded-[4px] bg-brand/15 text-brand" aria-hidden="true">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $gear !!}</svg>
                    </span>
                @endif

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-display text-base font-bold italic leading-tight">{{ $user->name }}</span>
                    <span class="block truncate font-mono text-[10px] tracking-[0.08em] text-white/60">{{ $describe($guard, $user) }}</span>
                </span>

                @if ($inUse)
                    <span class="inline-grid shrink-0 -skew-x-12 rounded-[3px] bg-brand px-2 py-0.5"><span class="skew-x-12 font-mono text-[9px] font-bold tracking-[0.1em] text-ink">EN USO</span></span>
                @else
                    <form method="POST" action="{{ route('site.account.switch') }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="guard" value="{{ $guard }}">
                        <button type="submit"
                                class="-skew-x-12 rounded-[3px] bg-white/10 px-2.5 py-1 font-display text-xs font-bold uppercase italic tracking-[0.06em] text-white transition-colors hover:bg-brand hover:text-ink focus-visible:outline-2 focus-visible:outline-brand">
                            <span class="inline-block skew-x-12">Cambiar</span>
                        </button>
                    </form>
                @endif
            </div>
        @endforeach

        <div class="border-t border-white/10 py-1 text-sm">
            <a href="{{ url($activeGuard === 'club' ? '/club' : '/admin') }}"
               class="block px-4 py-2.5 text-white/80 hover:bg-white/[0.06] hover:text-white">
                Ir al panel {{ $activeGuard === 'club' ? 'de '.($coachClub?->short_name ?: 'mi club') : 'de administración' }}
            </a>

            @foreach ($missing as $guard)
                <a href="{{ route('site.account.add', $guard) }}"
                   class="flex items-center gap-2 px-4 py-2.5 text-white/80 hover:bg-white/[0.06] hover:text-white">
                    <span class="text-brand" aria-hidden="true">+</span>
                    Entrar con una cuenta de {{ $guard === 'club' ? 'técnico' : 'administración' }}
                </a>
            @endforeach

            <form method="POST" action="{{ route('site.account.logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-2.5 text-left text-white/80 hover:bg-white/[0.06] hover:text-rose-300">
                    Cerrar sesión de {{ $active?->name }}
                </button>
            </form>
        </div>
    </div>
</details>
