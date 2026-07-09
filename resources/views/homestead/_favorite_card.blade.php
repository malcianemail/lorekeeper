@php
    $subject = $favorite->subject;
    $isSpace = in_array($favorite->ref_type, [\App\Models\Homestead\HomesteadFavorite::TYPE_ROOM, \App\Models\Homestead\HomesteadFavorite::TYPE_HOUSE], true);
    $title = $isSpace ? ($subject ? $subject->name : 'Space') : ($subject ? $subject->fullName : 'Character');
    $owner = $subject && $subject->user ? $subject->user : null;
    $detailUrl = $isSpace
        ? ($favorite->showcase_url ?? url('showcase'))
        : ($subject ? $subject->url : '#');
@endphp

<div class="homestead-favorite-card card h-100 shadow-sm">
    <div class="homestead-favorite-card-media homestead-featured-card-media">
        @if($detailUrl !== '#')
            <a href="{{ $detailUrl }}" class="homestead-featured-card-media-link text-decoration-none text-body d-block">
        @endif
        @if($isSpace && !empty($preview))
            @include('homestead._featured_preview_mount', [
                'previewId' => 'favoritePreview' . $favorite->id,
                'preview' => $preview,
                'compact' => true,
            ])
        @elseif($favorite->ref_type === \App\Models\Homestead\HomesteadFavorite::TYPE_CHARACTER && $subject && $subject->image)
            <img src="{{ $subject->image->thumbnailUrl }}" class="homestead-featured-card-character-img" alt="{{ $title }}" loading="lazy">
        @else
            <div class="homestead-featured-card-fallback">
                <i class="fas fa-heart fa-2x text-muted"></i>
            </div>
        @endif
        <span class="badge badge-secondary homestead-featured-card-type">{{ \App\Services\Homestead\HomesteadConfig::favoriteTypeLabel($favorite->ref_type) }}</span>
        @if($detailUrl !== '#')
            </a>
        @endif
        <div class="homestead-favorite-card-action">
            @include('homestead._favorite_button', app(\App\Services\Homestead\FavoriteService::class)->getFavoriteButtonState(Auth::user(), $favorite->ref_type, $favorite->ref_id))
        </div>
    </div>
    <div class="card-body">
        <h2 class="h5 card-title mb-1">
            @if($detailUrl !== '#')
                <a href="{{ $detailUrl }}" class="text-body">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </h2>
        @if($owner)
            <p class="card-text small text-muted mb-0">by {!! $owner->displayName !!}</p>
        @endif
    </div>
</div>
