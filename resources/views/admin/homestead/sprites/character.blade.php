@extends('admin.layout')

@section('admin-title') Sprites: {{ $character->fullName }} @endsection

@section('admin-content')
{!! breadcrumbs(['Admin Panel' => 'admin', 'Character Sprites' => 'admin/homestead/sprites', $character->fullName => 'admin/homestead/sprites/character/' . $character->slug]) !!}

<h1>{{ $character->fullName }}</h1>
<p>
    Owner: {!! $character->user ? $character->user->displayName : '&mdash;' !!}
    &middot; Slots: <strong>{{ $slots['used'] }}</strong> / <strong>{{ $slots['max'] }}</strong>
    &middot; <a href="{{ url('admin/homestead/sprite-slots') }}?character={{ $character->slug }}">Edit slot limit</a>
    &middot; <a href="{{ $character->url . '/sprites' }}">View member page</a>
</p>

@if($slots['can_create'])
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Upload Sprite</h5></div>
        <div class="card-body">
            {!! Form::open(['url' => 'admin/homestead/sprites/character/' . $character->slug, 'files' => true]) !!}
                <div class="form-group">
                    {!! Form::label('name', 'Name (Optional)') !!}
                    {!! Form::text('name', null, ['class' => 'form-control']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('image', 'Image') !!}
                    <div>{!! Form::file('image', ['class' => 'form-control-file', 'accept' => 'image/jpeg,image/jpg,image/gif,image/png']) !!}</div>
                </div>
                {!! Form::submit('Upload Sprite', ['class' => 'btn btn-primary']) !!}
            {!! Form::close() !!}
        </div>
    </div>
@else
    <div class="alert alert-warning">{{ \App\Services\Homestead\HomesteadConfig::spriteSlotLimitMessage() }}</div>
@endif

@if($sprites->count())
    @if($sprites->count() > 1)
        {!! Form::open(['url' => 'admin/homestead/sprites/character/' . $character->slug . '/sort', 'class' => 'mb-3 text-right', 'id' => 'spriteSortForm']) !!}
            {!! Form::hidden('sort', '', ['id' => 'spriteSortOrder']) !!}
            {!! Form::submit('Save Order', ['class' => 'btn btn-primary btn-sm']) !!}
        {!! Form::close() !!}
    @endif

    <div class="row {{ $sprites->count() > 1 ? 'sprite-sortable' : '' }}" id="spriteGrid">
        @foreach($sprites as $sprite)
            @include('admin.homestead.sprites._sprite_card', ['sprite' => $sprite, 'character' => $character])
        @endforeach
    </div>
@else
    <p class="text-muted">No sprites uploaded yet.</p>
@endif
@endsection

@section('scripts')
@parent
<script>
$(document).ready(function() {
    $('.sprite-edit-toggle').on('click', function() {
        $($(this).data('target')).collapse('toggle');
    });

    @if($sprites->count() > 1)
    $('#spriteGrid').sortable({
        items: '.sprite-sort-item',
        placeholder: 'sortable-placeholder col-md-4 col-6 mb-3',
        update: function() {
            $('#spriteSortOrder').val($(this).sortable('toArray', {attribute: 'data-id'}));
        },
        start: function() {
            $('#spriteSortOrder').val($(this).sortable('toArray', {attribute: 'data-id'}));
        }
    });
    $('#spriteGrid').disableSelection();
    @endif
});
</script>
@endsection
