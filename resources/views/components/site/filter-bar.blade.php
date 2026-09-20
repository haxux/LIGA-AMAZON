@props(['action'])

{{-- Plain GET form, shared by the pages that filter: every selection is a
     shareable URL, and with JavaScript off the submit button still works. --}}
<form method="GET" action="{{ $action }}" class="mb-8 flex flex-wrap items-end gap-4 rounded-md bg-surface p-4">
    {{ $slot }}

    <noscript>
        <button type="submit" class="rounded-[4px] bg-brand px-4 py-2 font-display text-base font-bold uppercase tracking-[0.08em] text-ink">Filtrar</button>
    </noscript>
</form>
