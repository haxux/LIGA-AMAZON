{{-- Swap seam (Fase 6 design D5): when player avatars arrive from the external API,
     this file is the ONLY edit — wrap the span in @if with the resolved image URL.
     No avatar column exists today, so no conditional is written yet. --}}
@props(['player'])

<span {{ $attributes->merge(['class' => 'size-8 shrink-0 rounded-full bg-surface-muted']) }}
      aria-hidden="true"></span>
