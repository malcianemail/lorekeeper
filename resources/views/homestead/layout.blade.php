@extends('layouts.app')

@section('title')
    Homestead ::
    @yield('homestead-title')
@endsection

@section('sidebar')
    @include('homestead._sidebar')
@endsection

@section('content')
    @yield('homestead-content')
@endsection

@section('scripts')
@parent
@endsection
