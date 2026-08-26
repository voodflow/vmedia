@php
    $icon = $icon ?? 'file';
    $label = $label ?? 'FILE';
@endphp
<div
    class="vmedia-file-icon vmedia-file-icon--{{ $icon }}"
    role="img"
    aria-label="{{ $label }}"
>
    <span class="vmedia-file-icon__sheet" aria-hidden="true"></span>
    <span class="vmedia-file-icon__badge">{{ $label }}</span>
</div>
