@props(['fancy' => false])

{{-- El título de sección de una copa. En una internacional va centrado entre
     dos filetes del color del acento, como en las retransmisiones. --}}
@if ($fancy)
    <h2 {{ $attributes->merge(['class' => 'font-display mb-5 mt-2 flex items-center gap-4 text-xl font-bold uppercase tracking-[0.18em] text-ink']) }}>
        <span class="h-px flex-1 bg-gradient-to-r from-transparent to-brand/70" aria-hidden="true"></span>
        {{ $slot }}
        <span class="h-px flex-1 bg-gradient-to-l from-transparent to-brand/70" aria-hidden="true"></span>
    </h2>
@else
    <h2 {{ $attributes->merge(['class' => 'font-display mb-3 text-lg uppercase tracking-wide text-ink/70']) }}>{{ $slot }}</h2>
@endif
