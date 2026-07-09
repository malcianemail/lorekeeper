<li class="list-group-item">
    <a class="card-title h5 collapse-title" data-toggle="collapse" href="#activateSpriteSlotForm{{ $stack->first()->item_id }}">Activate Sprite Slot</a>
    <div id="activateSpriteSlotForm{{ $stack->first()->item_id }}" class="collapse">
        {!! Form::hidden('tag', $tag->tag) !!}
        <p>Permanently activate this sprite slot item for one of your characters. The character will be able to upload one additional sprite. Activated slots cannot be traded. This action is not reversible.</p>
        <p class="text-muted mb-2"><strong>Note:</strong> Select the item row above using the checkbox, then choose a character and click Activate.</p>
        <div class="form-group">
            {!! Form::label('character_id', 'Character') !!}
            {!! Form::select('character_id', ['' => 'Select Character'] + ($characterOptions ?? []), null, ['class' => 'form-control']) !!}
        </div>
        <div class="text-right">
            {!! Form::button('Activate', ['class' => 'btn btn-primary slot-activate-button', 'name' => 'action', 'value' => 'act', 'type' => 'submit']) !!}
        </div>
    </div>
</li>
