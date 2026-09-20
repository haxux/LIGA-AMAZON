@props(['club', 'size' => 'size-[22px]'])

@if ($club?->crest_path)
    <img src="{{ Storage::disk(config('filesystems.uploads'))->url($club->crest_path) }}" alt=""
         class="{{ $size }} shrink-0 rounded-[3px] object-cover">
@else
    <span class="{{ $size }} shrink-0 rounded-[3px] bg-surface-muted" aria-hidden="true"></span>
@endif
