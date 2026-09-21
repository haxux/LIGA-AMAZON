<x-layouts.site :title="'Chat — Liga Amazon'">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-wide text-ink">Chat</h1>
            <div class="font-mono text-[10px] tracking-[0.14em] text-ink/50">
                {{ $user->isAdmin() ? 'PRESIDENTE' : mb_strtoupper($user->club?->name ?? '') }}
            </div>
        </div>

        <p class="max-w-md text-xs text-ink/50">
            Uno a uno entre técnicos, y con los presidentes. Se refresca solo cada pocos segundos.
        </p>
    </div>

    <livewire:chat />
</x-layouts.site>
