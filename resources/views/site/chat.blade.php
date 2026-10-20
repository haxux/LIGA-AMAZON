<x-layouts.site :title="'Chat — Liga Amazon'">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <div>
            <h1 class="font-display -skew-x-6 text-4xl font-extrabold uppercase italic tracking-wide text-ink sm:text-5xl">Chat</h1>
            <div class="font-mono text-[10px] tracking-[0.14em] text-ink/50">
                {{ $user->isAdmin() ? 'PRESIDENTE' : mb_strtoupper($user->club?->name ?? '') }}
            </div>
        </div>

        <div class="flex max-w-md flex-col items-start gap-2">
            <p class="text-xs text-ink/50">
                Uno a uno entre técnicos, y con los presidentes. Se refresca solo cada pocos segundos.
            </p>
            <x-site.push-toggle />
        </div>
    </div>

    <livewire:chat />
</x-layouts.site>
