@extends('layouts.app')

@section('title')
    Homestead Editor ::
    @yield('editor-title')
@endsection

@section('content')
<div class="homestead-editor-wrapper">
    @yield('editor-content')
</div>
@endsection

@section('scripts')
@parent
@yield('editor-scripts')
@endsection
