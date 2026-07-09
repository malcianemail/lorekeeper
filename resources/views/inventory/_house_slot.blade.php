<li class="list-group-item">
    <a class="card-title h5 collapse-title" data-toggle="collapse" href="#activateHouseSlotForm{{ $stack->first()->item_id }}">Activate House Slot</a>
    <div id="activateHouseSlotForm{{ $stack->first()->item_id }}" class="collapse">
        {!! Form::hidden('tag', $tag->tag) !!}
        <p>Permanently activate this house slot item to unlock an additional outdoor house. Activated slots cannot be traded. This action is not reversible.</p>
        <div class="text-right">
            {!! Form::button('Activate', ['class' => 'btn btn-primary slot-activate-button', 'name' => 'action', 'value' => 'act', 'type' => 'submit']) !!}
        </div>
    </div>
</li>
