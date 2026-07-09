@extends('layouts.app')

@section('title') {{ $labels['title'] }} @endsection

@section('content')
{!! breadcrumbs([$labels['title'] => 'showcase']) !!}

<div class="homestead-showcase-header mb-3">
    <h1>{{ $labels['title'] }}</h1>
    <p class="mb-0">{{ $labels['description'] }}</p>
</div>

<ul class="nav nav-pills mb-4 homestead-showcase-filters">
    @foreach($typeFilters as $filterKey => $filterLabel)
        @php
            $filterUrl = $filterKey === 'all' ? url('showcase') : url('showcase?type=' . $filterKey);
            $isActive = ($filterKey === 'all' && !$activeType) || $activeType === $filterKey;
        @endphp
        <li class="nav-item">
            <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ $filterUrl }}">{{ $filterLabel }}</a>
        </li>
    @endforeach
</ul>

@if(!$featured->count())
    <div class="alert alert-secondary">
        <p class="mb-1"><strong>{{ $labels['empty_message'] }}</strong></p>
        <p class="mb-0 small">{{ $labels['empty_hint'] }}</p>
    </div>
@else
    <div class="row homestead-showcase-grid">
        @foreach($featured as $entry)
            <div class="col-md-4 col-sm-6 mb-4">
                @include('homestead._featured_card', ['featured' => $entry, 'preview' => $entry->preview_data ?? null, 'favoriteState' => $entry->favorite_state ?? null])
            </div>
        @endforeach
    </div>

    {!! $featured->appends(['type' => $activeType])->render() !!}
@endif
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/homestead-room-preview.js') }}?v={{ filemtime(public_path('js/homestead-room-preview.js')) }}"></script>
@if(Auth::check())
<script src="{{ asset('js/homestead-favorite-button.js') }}?v={{ filemtime(public_path('js/homestead-favorite-button.js')) }}"></script>
@endif
@endsection
