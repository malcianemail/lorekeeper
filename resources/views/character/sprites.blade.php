@extends('character.layout', ['isMyo' => $character->is_myo_slot])

@section('profile-title') {{ $character->fullName }}'s Sprites @endsection

@section('meta-img') {{ $character->image->thumbnailUrl }} @endsection

@section('profile-content')
{!! breadcrumbs([($character->category->masterlist_sub_id ? $character->category->sublist->name.' Masterlist' : 'Character masterlist') => ($character->category->masterlist_sub_id ? 'sublist/'.$character->category->sublist->key : 'masterlist' ), $character->fullName => $character->url, 'Sprites' => $character->url . '/sprites']) !!}

@include('character._header', ['character' => $character])

<p class="mb-3">Sprites are character images used for homestead room placement. Upload different poses or outfits for your character.</p>

@if($canManage)
    <p class="mb-3">
        You are using <strong>{{ $slots['used'] }}</strong> of <strong>{{ $slots['max'] }}</strong> sprite slot{{ $slots['max'] == 1 ? '' : 's' }}.
        @if($slots['remaining'] > 0)
            <strong>{{ $slots['remaining'] }}</strong> slot{{ $slots['remaining'] == 1 ? '' : 's' }} remaining.
        @endif
    </p>
@endif

@if($canManage && $slots['can_create'])
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Upload Sprite</h5>
        </div>
        <div class="card-body">
            {!! Form::open(['url' => $character->url . '/sprites', 'files' => true]) !!}
                <div class="form-group">
                    {!! Form::label('name', 'Name (Optional)') !!}
                    {!! Form::text('name', null, ['class' => 'form-control', 'placeholder' => 'e.g. Standing, Sitting, Winter outfit']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('image', 'Image') !!} {!! add_help('Accepted formats: JPG, JPEG, GIF, PNG. Max size: 20MB.') !!}
                    <div>{!! Form::file('image', ['class' => 'form-control-file', 'accept' => 'image/jpeg,image/jpg,image/gif,image/png']) !!}</div>
                </div>
                <div class="text-right">
                    {!! Form::submit('Upload Sprite', ['class' => 'btn btn-primary']) !!}
                </div>
            {!! Form::close() !!}
        </div>
    </div>
@elseif($canManage)
    <div class="alert alert-warning">{{ \App\Services\Homestead\HomesteadConfig::spriteSlotLimitMessage() }}</div>
@endif

<h3 class="mb-3">Sprites</h3>

@if($sprites->count())
    @if($canManage && $sprites->count() > 1)
        {!! Form::open(['url' => $character->url . '/sprites/sort', 'class' => 'mb-3 text-right', 'id' => 'spriteSortForm']) !!}
            {!! Form::hidden('sort', '', ['id' => 'spriteSortOrder']) !!}
            {!! Form::submit('Save Order', ['class' => 'btn btn-primary btn-sm']) !!}
        {!! Form::close() !!}
    @endif

    <div class="row @if($canManage && $sprites->count() > 1) sprite-sortable @endif" id="spriteGrid">
        @foreach($sprites as $sprite)
            @include('character._sprite_card', ['sprite' => $sprite])
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
            var target = $(this).data('target');
            $(target).collapse('toggle');
        });
    });
</script>
@if($canManage && $sprites->count() > 1)
<script>
    $(document).ready(function() {
        $('#spriteGrid').sortable({
            items: '.sprite-sort-item',
            placeholder: 'sortable-placeholder col-md-4 col-6 mb-3',
            tolerance: 'pointer',
            stop: function() {
                var order = [];
                $('#spriteGrid .sprite-sort-item').each(function() {
                    order.push($(this).data('id'));
                });
                $('#spriteSortOrder').val(order.join(','));
            },
            create: function() {
                var order = [];
                $('#spriteGrid .sprite-sort-item').each(function() {
                    order.push($(this).data('id'));
                });
                $('#spriteSortOrder').val(order.join(','));
            }
        });
    });
</script>
@endif
@endsection
