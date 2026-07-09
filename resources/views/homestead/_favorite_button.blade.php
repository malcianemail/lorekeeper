@php
    $refType = $refType ?? null;
    $refId = $refId ?? null;
    $isFavorited = !empty($isFavorited);
    $count = isset($count) ? (int) $count : 0;
    $canFavorite = $canFavorite ?? Auth::check();
    $size = $size ?? 'sm';
    $buttonClass = 'btn btn-outline-danger homestead-favorite-button homestead-favorite-button-' . $size . ($isFavorited ? ' is-favorited' : '');
@endphp

@if($canFavorite && $refType && $refId)
    <button
        type="button"
        class="{{ $buttonClass }}"
        data-ref-type="{{ $refType }}"
        data-ref-id="{{ $refId }}"
        data-favorited="{{ $isFavorited ? '1' : '0' }}"
        title="{{ $isFavorited ? 'Remove from favorites' : 'Add to favorites' }}"
        aria-pressed="{{ $isFavorited ? 'true' : 'false' }}"
    >
        <i class="{{ $isFavorited ? 'fas' : 'far' }} fa-heart"></i>
        <span class="homestead-favorite-count">{{ $count }}</span>
    </button>
@else
    <span class="homestead-favorite-count-display text-muted" title="Favorite count">
        <i class="fas fa-heart"></i> {{ $count }}
    </span>
@endif
