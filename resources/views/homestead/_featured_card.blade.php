@php
    $subject = $featured->subject;
    $isSpace = in_array($featured->type, [\App\Models\Homestead\FeaturedItem::TYPE_ROOM, \App\Models\Homestead\FeaturedItem::TYPE_HOUSE], true);
    $title = $isSpace ? $subject->name : ($subject ? $subject->fullName : 'Featured');
    $owner = $featured->ownerUser ?: ($subject && $subject->user ? $subject->user : null);
@endphp

<div class="homestead-featured-card card h-100 shadow-sm">
    <div class="homestead-featured-card-media">
        <a href="{{ $featured->url }}" class="homestead-featured-card-media-link text-decoration-none text-body d-block">
            @if($isSpace && $preview)
                @include('homestead._featured_preview_mount', [
                    'previewId' => 'featuredPreview' . $featured->id,
                    'preview' => $preview,
                    'compact' => true,
                ])
            @elseif($featured->type === \App\Models\Homestead\FeaturedItem::TYPE_CHARACTER && $subject && $subject->image)
                <img src="{{ $subject->image->thumbnailUrl }}" class="homestead-featured-card-character-img" alt="{{ $title }}" loading="lazy">
            @else
                <div class="homestead-featured-card-fallback">
                    <i class="fas fa-star fa-2x text-muted"></i>
                </div>
            @endif
            <span class="badge badge-primary homestead-featured-card-type">{{ $featured->type_label }}</span>
        </a>
        @if(!empty($favoriteState))
            <div class="homestead-featured-card-favorite">
                @include('homestead._favorite_button', $favoriteState)
            </div>
        @endif
    </div>
    <a href="{{ $featured->url }}" class="homestead-featured-card-body-link text-decoration-none text-body">
        <div class="card-body">
            <h2 class="h5 card-title mb-1">{{ $title }}</h2>
            @if($owner)
                <p class="card-text small text-muted mb-2">by {!! $owner->displayName !!}</p>
            @endif
            @if($featured->note)
                <p class="card-text small mb-0">{{ \Illuminate\Support\Str::limit($featured->note, 120) }}</p>
            @endif
        </div>
    </a>
</div>
