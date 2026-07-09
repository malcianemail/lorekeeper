@extends('admin.layout')

@section('admin-title') Sprite Slots @endsection

@section('admin-content')
{!! breadcrumbs(['Admin Panel' => 'admin', 'Sprite Slots' => 'admin/homestead/sprite-slots']) !!}

<h1>Sprite Slots</h1>
<p>Manage per-character sprite upload limits. Base slot count is <strong>{{ $baseSlots }}</strong>. Users can also unlock slots with <code>sprite_slot</code> inventory items.</p>

{!! Form::open(['method' => 'GET', 'class' => 'form-inline justify-content-end mb-3']) !!}
    <div class="form-group mr-2">
        {!! Form::text('character', Request::get('character'), ['class' => 'form-control', 'placeholder' => 'Character slug or number']) !!}
    </div>
    <div class="form-group">
        {!! Form::submit('Search', ['class' => 'btn btn-primary']) !!}
    </div>
{!! Form::close() !!}

@if(!$characters->count())
    <p>No characters found.</p>
@else
    {!! $characters->render() !!}

    <table class="table table-sm">
        <thead>
            <tr>
                <th>Character</th>
                <th>Owner</th>
                <th>Used</th>
                <th>Max Slots</th>
                <th>Remaining</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($characters as $character)
                <tr>
                    <td><a href="{{ $character->url }}">{!! $character->displayName !!}</a></td>
                    <td>{!! $character->user ? $character->user->displayName : '&mdash;' !!}</td>
                    <td>{{ $character->slot_summary['used'] }}</td>
                    <td>
                        {!! Form::open(['url' => 'admin/homestead/sprite-slots/edit/' . $character->id, 'class' => 'form-inline d-inline']) !!}
                            {!! Form::number('max_sprite_slots', $character->max_sprite_slots, ['class' => 'form-control form-control-sm mr-2', 'min' => $baseSlots, 'max' => 999, 'style' => 'width: 90px;']) !!}
                            {!! Form::submit('Save', ['class' => 'btn btn-primary btn-sm']) !!}
                        {!! Form::close() !!}
                    </td>
                    <td>{{ $character->slot_summary['remaining'] }}</td>
                    <td class="text-right text-nowrap">
                        <a href="{{ url('admin/homestead/sprites/character/' . $character->slug) }}" class="btn btn-outline-secondary btn-sm">Sprites</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {!! $characters->render() !!}
@endif
@endsection
