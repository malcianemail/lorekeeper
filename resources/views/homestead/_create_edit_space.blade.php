@php($segment = $labels['segment'])
{!! Form::open(['url' => $space->id ? 'homestead/'.$segment.'/edit/'.$space->id : 'homestead/'.$segment.'/create', 'class' => 'homestead-space-form']) !!}
    @if($space->id)
        {!! Form::hidden('room_id', $space->id) !!}
    @endif
    <div class="form-group">
        {!! Form::label('name', $labels['name_label']) !!}
        {!! Form::text('name', old('name', $space->name), [
            'class' => 'form-control' . ($errors->has('name') ? ' is-invalid' : ''),
            'placeholder' => $labels['name_placeholder'],
            'maxlength' => 100,
            'required' => true,
            'autofocus' => true,
        ]) !!}
        @if($errors->has('name'))
            <div class="invalid-feedback d-block">{{ $errors->first('name') }}</div>
        @endif
    </div>
    <div class="text-right">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        {!! Form::submit($space->id ? 'Update' : 'Create', ['class' => 'btn btn-primary']) !!}
    </div>
{!! Form::close() !!}
