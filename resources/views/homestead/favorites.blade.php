@extends('homestead.layout')

@section('homestead-title') {{ $labels['title'] }} @endsection

@section('homestead-content')
{!! breadcrumbs(['Homestead' => 'homestead/rooms', $labels['title'] => 'favorites']) !!}

<div class="homestead-favorites-header mb-3">
    <h1>{{ $labels['title'] }}</h1>
    <p class="mb-0">{{ $labels['description'] }}</p>
</div>

<ul class="nav nav-pills mb-4 homestead-favorites-filters">
    @foreach($typeFilters as $filterKey => $filterLabel)
        @php
            $filterUrl = $filterKey === 'all' ? url('favorites') : url('favorites?type=' . $filterKey);
            $isActive = ($filterKey === 'all' && !$activeType) || $activeType === $filterKey;
        @endphp
        <li class="nav-item">
            <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ $filterUrl }}">{{ $filterLabel }}</a>
        </li>
    @endforeach
</ul>

@if(!$favorites->count())
    <div class="alert alert-secondary">
        <p class="mb-1"><strong>{{ $labels['empty_message'] }}</strong></p>
        <p class="mb-0 small">{{ $labels['empty_hint'] }}</p>
    </div>
@else
    <div class="row homestead-favorites-grid">
        @foreach($favorites as $favorite)
            <div class="col-md-4 col-sm-6 mb-4">
                @include('homestead._favorite_card', [
                    'favorite' => $favorite,
                    'preview' => $favorite->preview_data ?? null,
                ])
            </div>
        @endforeach
    </div>

    {!! $favorites->appends(['type' => $activeType])->render() !!}
@endif
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/homestead-room-preview.js') }}?v={{ filemtime(public_path('js/homestead-room-preview.js')) }}"></script>
<script src="{{ asset('js/homestead-favorite-button.js') }}?v={{ filemtime(public_path('js/homestead-favorite-button.js')) }}"></script>
@endsection
