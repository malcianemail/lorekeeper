@extends('admin.layout')

@section('admin-title') Character Sprites @endsection

@section('admin-content')
{!! breadcrumbs(['Admin Panel' => 'admin', 'Character Sprites' => 'admin/homestead/sprites']) !!}

<h1>Character Sprites</h1>
<p>Review and manage uploaded character sprites used in the homestead editor.</p>

{!! Form::open(['method' => 'GET', 'class' => 'form-inline justify-content-end mb-3']) !!}
    <div class="form-group mr-2">
        {!! Form::text('character', Request::get('character'), ['class' => 'form-control', 'placeholder' => 'Character slug or number']) !!}
    </div>
    <div class="form-group">
        {!! Form::submit('Search', ['class' => 'btn btn-primary']) !!}
    </div>
{!! Form::close() !!}

@if(!$sprites->count())
    <p>No sprites found.</p>
@else
    {!! $sprites->render() !!}

    <table class="table table-sm">
        <thead>
            <tr>
                <th>Preview</th>
                <th>Sprite</th>
                <th>Character</th>
                <th>Owner</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($sprites as $sprite)
                <tr>
                    <td style="width: 72px;">
                        @if($sprite->imageUrl)
                            <img src="{{ $sprite->imageUrl }}" alt="{{ $sprite->displayName }}" class="img-thumbnail" style="max-height: 64px;">
                        @else
                            <span class="text-muted small">No image</span>
                        @endif
                    </td>
                    <td>
                        {{ $sprite->displayName }}
                        @if($sprite->character && $sprite->character->active_sprite_id == $sprite->id)
                            <span class="badge badge-primary">Active</span>
                        @endif
                    </td>
                    <td>
                        @if($sprite->character)
                            <a href="{{ $sprite->character->url }}">{!! $sprite->character->displayName !!}</a>
                        @else
                            <span class="text-muted">Unavailable</span>
                        @endif
                    </td>
                    <td>{!! $sprite->character && $sprite->character->user ? $sprite->character->user->displayName : '&mdash;' !!}</td>
                    <td class="text-right">
                        @if($sprite->character)
                            <a href="{{ url('admin/homestead/sprites/character/' . $sprite->character->slug) }}" class="btn btn-primary btn-sm">Manage</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {!! $sprites->render() !!}
@endif
@endsection
