@extends('user.layout')

@section('profile-title') {{ $user->name }}'s {{ \App\Services\Homestead\HomesteadConfig::favoriteLabel('profile_title') }} @endsection

@section('profile-content')
{!! breadcrumbs(['Users' => 'users', $user->name => $user->url, \App\Services\Homestead\HomesteadConfig::favoriteLabel('profile_title') => $user->url . '/homestead-favorites']) !!}

<h1>{{ \App\Services\Homestead\HomesteadConfig::favoriteLabel('profile_title') }}</h1>
<p>These are {{ Auth::check() && Auth::user()->id == $user->id ? 'your' : $user->name . '\'s' }} saved homestead rooms, houses, and characters.</p>

@if(isset($favorites) && $favorites->count())
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

    {!! $favorites->render() !!}
@else
    <p>No homestead favorites found.</p>
@endif
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/homestead-room-preview.js') }}?v={{ filemtime(public_path('js/homestead-room-preview.js')) }}"></script>
@if(Auth::check())
<script src="{{ asset('js/homestead-favorite-button.js') }}?v={{ filemtime(public_path('js/homestead-favorite-button.js')) }}"></script>
@endif
@endsection
