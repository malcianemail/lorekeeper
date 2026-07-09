@extends('layouts.app')

@section('title') {{ $featured->type_label }}: {{ $featured->subject ? ($featured->type === \App\Models\Homestead\FeaturedItem::TYPE_CHARACTER ? $featured->subject->fullName : $featured->subject->name) : 'Featured' }} @endsection

@section('content')
@php
    $subject = $featured->subject;
    $isCharacter = $featured->type === \App\Models\Homestead\FeaturedItem::TYPE_CHARACTER;
    $title = $isCharacter ? ($subject ? $subject->fullName : 'Character') : ($subject ? $subject->name : 'Space');
    $owner = $featured->ownerUser ?: ($subject && $subject->user ? $subject->user : null);
@endphp

{!! breadcrumbs([$labels['title'] => 'showcase', $title => $featured->url]) !!}

<div class="homestead-showcase-detail mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between mb-3">
        <div>
            <span class="badge badge-primary mb-2">{{ $featured->type_label }}</span>
            <h1 class="mb-1">{{ $title }}</h1>
            @if($owner)
                <p class="text-muted mb-0">Created by {!! $owner->displayName !!}</p>
            @endif
        </div>
        <div class="mt-2 mt-md-0 d-flex flex-wrap align-items-center">
            @if(!empty($favoriteState))
                <span class="mr-2 mb-2">@include('homestead._favorite_button', array_merge($favoriteState, ['size' => 'md']))</span>
            @endif
            @if($isCharacter && $subject)
                <a href="{{ $subject->url }}" class="btn btn-outline-primary btn-sm">View Character</a>
            @endif
            <a href="{{ url('showcase') }}" class="btn btn-outline-secondary btn-sm">Back to Showcase</a>
        </div>
    </div>

    @if($featured->note)
        <div class="alert alert-info homestead-showcase-note">
            {!! nl2br(e($featured->note)) !!}
        </div>
    @endif

    <div class="homestead-showcase-detail-media card shadow-sm">
        <div class="card-body">
            @if($preview)
                @include('homestead._featured_preview_mount', [
                    'previewId' => 'featuredDetailPreview' . $featured->id,
                    'preview' => $preview,
                    'compact' => false,
                ])
            @elseif($isCharacter && $subject && $subject->image)
                <div class="text-center">
                    <a href="{{ $subject->image->imageUrl }}" data-lightbox="featured-character" data-title="{{ $title }}">
                        <img src="{{ $subject->image->imageUrl }}" class="img-fluid homestead-showcase-character-image" alt="{{ $title }}">
                    </a>
                </div>
            @else
                <div class="homestead-featured-card-fallback py-5 text-center">
                    <i class="fas fa-star fa-3x text-muted"></i>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
@parent
@if($preview)
<script src="{{ asset('js/homestead-room-preview.js') }}?v={{ filemtime(public_path('js/homestead-room-preview.js')) }}"></script>
@endif
@if(Auth::check())
<script src="{{ asset('js/homestead-favorite-button.js') }}?v={{ filemtime(public_path('js/homestead-favorite-button.js')) }}"></script>
@endif
@endsection
