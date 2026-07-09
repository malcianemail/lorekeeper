@extends('admin.layout')

@section('admin-title') {{ $featured->exists ? 'Edit Featured' : 'Add Featured' }} @endsection

@section('admin-content')
{!! breadcrumbs([
    'Admin Panel' => 'admin',
    \App\Services\Homestead\HomesteadConfig::featuredLabel('admin_title') => 'admin/homestead/featured',
    ($featured->exists ? 'Edit' : 'Add') => $featured->exists ? 'admin/homestead/featured/edit/' . $featured->id : 'admin/homestead/featured/create',
]) !!}

<h1>{{ $featured->exists ? 'Edit Featured Entry' : 'Add Featured Entry' }}</h1>

@include('admin.homestead._featured_nav')

@if($featured->exists)
    {!! Form::open(['url' => 'admin/homestead/featured/edit/' . $featured->id]) !!}
@else
    {!! Form::open(['url' => 'admin/homestead/featured/create']) !!}
@endif

<div class="card mb-3">
    <div class="card-body">
        @unless($featured->exists)
            <div class="form-group">
                {!! Form::label('type', 'Featured Type') !!}
                {!! Form::select('type', [
                    \App\Models\Homestead\FeaturedItem::TYPE_ROOM => 'Room',
                    \App\Models\Homestead\FeaturedItem::TYPE_HOUSE => 'House',
                    \App\Models\Homestead\FeaturedItem::TYPE_CHARACTER => 'Character',
                ], old('type'), ['class' => 'form-control', 'id' => 'featuredType']) !!}
            </div>

            <div class="form-group featured-ref-group" data-type="room">
                {!! Form::label('ref_id', 'Room') !!}
                {!! Form::select('ref_id', $rooms->mapWithKeys(function ($room) {
                    $owner = $room->user ? $room->user->name : 'Unknown';
                    return [$room->id => $room->name . ' (' . $owner . ')'];
                })->prepend('Select a room', ''), old('ref_id'), ['class' => 'form-control featured-ref-select', 'data-type' => 'room']) !!}
            </div>

            <div class="form-group featured-ref-group d-none" data-type="house">
                {!! Form::label('ref_id', 'House') !!}
                {!! Form::select('ref_id', $houses->mapWithKeys(function ($house) {
                    $owner = $house->user ? $house->user->name : 'Unknown';
                    return [$house->id => $house->name . ' (' . $owner . ')'];
                })->prepend('Select a house', ''), null, ['class' => 'form-control featured-ref-select', 'data-type' => 'house', 'disabled' => true]) !!}
            </div>

            <div class="form-group featured-ref-group d-none" data-type="character">
                {!! Form::label('ref_id', 'Character') !!}
                {!! Form::select('ref_id', $characters->mapWithKeys(function ($character) {
                    $owner = $character->user ? $character->user->name : 'Unknown';
                    return [$character->id => $character->fullName . ' (' . $owner . ')'];
                })->prepend('Select a character', ''), null, ['class' => 'form-control featured-ref-select', 'data-type' => 'character', 'disabled' => true]) !!}
            </div>
        @else
            <div class="form-group">
                <label>Type</label>
                <div>{{ $featured->type_label }}</div>
            </div>
            <div class="form-group">
                <label>Content</label>
                <div>
                    @if($featured->subject)
                        @if($featured->type === \App\Models\Homestead\FeaturedItem::TYPE_CHARACTER)
                            {{ $featured->subject->fullName }}
                        @else
                            {{ $featured->subject->name }}
                        @endif
                    @else
                        <span class="text-danger">Content no longer available</span>
                    @endif
                </div>
            </div>
        @endunless

        <div class="form-group">
            {!! Form::label('note', 'Featured Note') !!}
            {!! Form::textarea('note', old('note', $featured->note), ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Optional moderator caption shown on the showcase']) !!}
        </div>

        <div class="form-group">
            {!! Form::label('featured_order', 'Display Order') !!}
            {!! Form::number('featured_order', old('featured_order', $featured->featured_order), ['class' => 'form-control', 'min' => 0, 'max' => 9999]) !!}
            {!! add_help('Lower numbers appear first in the showcase.') !!}
        </div>

        <div class="form-group">
            {!! Form::label('is_active', 'Visible in Showcase') !!}
            {!! Form::select('is_active', [1 => 'Active', 0 => 'Hidden'], old('is_active', $featured->exists ? (int) $featured->is_active : 1), ['class' => 'form-control']) !!}
        </div>
    </div>
</div>

{!! Form::submit($featured->exists ? 'Save Changes' : 'Add to Featured', ['class' => 'btn btn-primary']) !!}
<a href="{{ url('admin/homestead/featured') }}" class="btn btn-secondary">Cancel</a>
{!! Form::close() !!}

@if($featured->exists)
    <div class="mt-4">
        {!! Form::open(['url' => 'admin/homestead/featured/delete/' . $featured->id, 'id' => 'deleteFeaturedForm']) !!}
            {!! Form::submit('Remove from Featured', ['class' => 'btn btn-danger', 'onclick' => 'return confirm("Remove this featured entry?")']) !!}
        {!! Form::close() !!}
    </div>
@endif
@endsection

@section('scripts')
@parent
@unless($featured->exists)
<script>
$(document).ready(function() {
    function syncFeaturedRefSelects() {
        var type = $('#featuredType').val();
        $('.featured-ref-group').addClass('d-none');
        $('.featured-ref-select').prop('disabled', true).prop('name', '');

        var $group = $('.featured-ref-group[data-type="' + type + '"]');
        $group.removeClass('d-none');
        $group.find('.featured-ref-select').prop('disabled', false).prop('name', 'ref_id');
    }

    $('#featuredType').on('change', syncFeaturedRefSelects);
    syncFeaturedRefSelects();
});
</script>
@endunless
@endsection
