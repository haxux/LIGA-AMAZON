@props(['name', 'label', 'options', 'selected' => null])

<label class="min-w-[180px] flex-1 sm:max-w-[260px]">
    <span class="mb-1 block font-mono text-[10px] tracking-[0.12em] text-white/50">{{ $label }}</span>

    <select name="{{ $name }}" onchange="this.form.submit()"
            class="w-full rounded-[4px] border border-white/10 bg-surface-alt px-3 py-2 font-display text-base font-semibold uppercase tracking-[0.06em] text-white">
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) $value === (string) $selected)>{{ $text }}</option>
        @endforeach
    </select>
</label>
