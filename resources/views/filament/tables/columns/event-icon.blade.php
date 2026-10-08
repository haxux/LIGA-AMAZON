@php
    $record = $getRecord();
@endphp

{{-- El mismo icono que el sitio público, para que el panel y la página hablen
     el mismo idioma. Con el nombre al lado: aquí se están CARGANDO datos, y un
     icono solo obliga a recordar cuál era cuál. --}}
<div class="flex items-center gap-2">
    <x-event-icon :type="$record->type" :second-yellow="$record->isSecondYellow()" size="size-6" />

    <span @class([
        'text-sm',
        'text-danger-600 dark:text-danger-400' => $record->isSecondYellow(),
        'text-gray-700 dark:text-gray-300' => ! $record->isSecondYellow(),
    ])>
        {{ $record->isSecondYellow()
            ? 'Doble amarilla · expulsado'
            : (\App\Models\GameEvent::TYPES[$record->type] ?? $record->type) }}
    </span>
</div>
