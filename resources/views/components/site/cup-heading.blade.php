@props(['fancy' => false])

{{-- El título de sección de una copa. En una internacional va centrado entre
     dos filetes del color del acento, como en las retransmisiones. --}}
@if ($fancy)
    <h2 {{ $attributes->merge(['class' => 'font-display mb-5 mt-2 flex items-center gap-4 text-xl font-bold uppercase italic tracking-[0.18em] text-ink']) }}>
        <span class="h-px flex-1 bg-gradient-to-r from-transparent to-brand/70" aria-hidden="true"></span>
        {{ $slot }}
        <span class="h-px flex-1 bg-gradient-to-l from-transparent to-brand/70" aria-hidden="true"></span>
    </h2>
@else
    <h2 {{ $attributes->merge(['class' => 'font-display mb-4 mt-2 -skew-x-6 text-3xl font-extrabold uppercase italic tracking-wide text-ink']) }}>{{ $slot }}</h2>
@endif
