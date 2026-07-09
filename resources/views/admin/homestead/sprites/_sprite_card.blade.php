@php($baseUrl = 'admin/homestead/sprites/character/' . $character->slug)
<div class="col-md-4 col-6 mb-3 sprite-sort-item" data-id="{{ $sprite->id }}">
    <div class="card h-100 character-sprite-card {{ $character->active_sprite_id == $sprite->id ? 'border-primary' : '' }}">
        <div class="card-body p-2 text-center">
            @if($sprite->imageUrl)
                <a href="{{ $sprite->imageUrl }}" data-lightbox="admin-sprites" data-title="{{ $sprite->displayName }}">
                    <img src="{{ $sprite->imageUrl }}" class="img-fluid character-sprite-thumb mb-2" alt="{{ $sprite->displayName }}" />
                </a>
            @else
                <div class="character-sprite-thumb character-sprite-thumb-empty mb-2 text-muted">No image</div>
            @endif

            <div class="mb-2">
                <strong>{{ $sprite->displayName }}</strong>
                @if($character->active_sprite_id == $sprite->id)
                    <span class="badge badge-primary ml-1">Active</span>
                @endif
            </div>

            <div class="btn-group btn-group-sm mb-2" role="group">
                @if($character->active_sprite_id != $sprite->id && $sprite->has_image)
                    {!! Form::open(['url' => $baseUrl . '/' . $sprite->id . '/active', 'class' => 'd-inline']) !!}
                        {!! Form::submit('Set Active', ['class' => 'btn btn-outline-primary btn-sm']) !!}
                    {!! Form::close() !!}
                @endif
                <button type="button" class="btn btn-outline-secondary btn-sm sprite-edit-toggle" data-target="#sprite-edit-{{ $sprite->id }}">Edit</button>
                {!! Form::open(['url' => $baseUrl . '/' . $sprite->id . '/delete', 'class' => 'd-inline', 'onsubmit' => 'return confirm("Delete this sprite?")']) !!}
                    {!! Form::submit('Delete', ['class' => 'btn btn-outline-danger btn-sm']) !!}
                {!! Form::close() !!}
            </div>

            <div class="collapse text-left" id="sprite-edit-{{ $sprite->id }}">
                {!! Form::open(['url' => $baseUrl . '/' . $sprite->id . '/edit', 'files' => true]) !!}
                    <div class="form-group mb-2">
                        {!! Form::label('name', 'Name (Optional)', ['class' => 'mb-0 small']) !!}
                        {!! Form::text('name', $sprite->name, ['class' => 'form-control form-control-sm']) !!}
                    </div>
                    <div class="form-group mb-2">
                        {!! Form::label('image', 'Replace Image (Optional)', ['class' => 'mb-0 small']) !!}
                        <div>{!! Form::file('image', ['class' => 'form-control-file form-control-sm', 'accept' => 'image/jpeg,image/jpg,image/gif,image/png']) !!}</div>
                    </div>
                    <div class="text-right">
                        {!! Form::submit('Save', ['class' => 'btn btn-primary btn-sm']) !!}
                    </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
