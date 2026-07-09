@extends('admin.layout')

@section('admin-title') {{ $labels['title'] }} @endsection

@section('admin-content')
{!! breadcrumbs(['Admin Panel' => 'admin', $labels['title'] => 'admin/homestead/featured']) !!}

<h1>{{ $labels['title'] }}</h1>
<p>{{ $labels['description'] }}</p>

@include('admin.homestead._featured_nav')

<div class="text-right mb-3">
    <a class="btn btn-primary" href="{{ url('admin/homestead/featured/create' . ($activeType ? '?type=' . $activeType : '')) }}"><i class="fas fa-plus"></i> Add Featured</a>
</div>

@if(!$featured->count())
    <p>No featured entries yet.</p>
@else
    <table class="table table-sm featured-items-table">
        <thead>
            <tr>
                <th></th>
                <th>Order</th>
                <th>Type</th>
                <th>Content</th>
                <th>Owner</th>
                <th>Note</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="sortable" class="sortable">
            @foreach($featured as $entry)
                @include('admin.homestead.featured._row', ['entry' => $entry])
            @endforeach
        </tbody>
    </table>

    {!! Form::open(['url' => 'admin/homestead/featured/sort']) !!}
        {!! Form::hidden('sort', '', ['id' => 'sortableOrder']) !!}
        {!! Form::submit('Save Order', ['class' => 'btn btn-primary']) !!}
    {!! Form::close() !!}
@endif
@endsection

@section('scripts')
@parent
<script>
$(document).ready(function() {
    $('#sortable').sortable({
        items: '.sort-item',
        handle: '.handle',
        placeholder: 'sortable-placeholder',
        update: function() {
            $('#sortableOrder').val($(this).sortable('toArray', {attribute: 'data-id'}));
        },
        start: function() {
            $('#sortableOrder').val($(this).sortable('toArray', {attribute: 'data-id'}));
        }
    });
    $('#sortable').disableSelection();
});
</script>
@endsection
