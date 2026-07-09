@php($segment = $labels['segment'])
{!! Form::open(['url' => 'homestead/'.$segment.'/delete/'.$space->id, 'class' => 'homestead-space-form']) !!}
    <p>{!! str_replace(':name', e($space->name), $labels['delete_confirm']) !!}</p>
    <div class="text-right">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        {!! Form::submit($labels['delete_action'], ['class' => 'btn btn-danger']) !!}
    </div>
{!! Form::close() !!}
