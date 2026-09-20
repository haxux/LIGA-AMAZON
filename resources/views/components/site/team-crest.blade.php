@props(['team', 'size' => 'size-[22px]'])

@if ($team->crest_path)
    <img src="{{ Storage::disk(config('filesystems.uploads'))->url($team->crest_path) }}" alt=""
         class="{{ $size }} shrink-0 rounded-[3px] object-cover">
@else
    <span class="{{ $size }} shrink-0 rounded-[3px] bg-surface-muted" aria-hidden="true"></span>
@endif
