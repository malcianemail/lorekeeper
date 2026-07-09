@php
    $subject = $entry->subject;
    $title = 'Missing content';
    if ($subject) {
        $title = $entry->type === \App\Models\Homestead\FeaturedItem::TYPE_CHARACTER
            ? $subject->fullName
            : $subject->name;
    }
    $owner = $entry->ownerUser ?: ($subject && $subject->user ? $subject->user : null);
@endphp

<tr class="sort-item" data-id="{{ $entry->id }}">
    <td><a class="fas fa-arrows-alt-v handle" href="#"></a></td>
    <td>{{ $entry->featured_order }}</td>
    <td>{{ $entry->type_label }}</td>
    <td>
        <a href="{{ $entry->url }}" target="_blank">{{ $title }}</a>
        @unless($subject)
            <span class="badge badge-warning ml-1">Unavailable</span>
        @endunless
    </td>
    <td>{!! $owner ? $owner->displayName : '&mdash;' !!}</td>
    <td>{!! $entry->note ? e(\Illuminate\Support\Str::limit($entry->note, 60)) : '&mdash;' !!}</td>
    <td>
        @if($entry->is_active)
            <span class="badge badge-success">Active</span>
        @else
            <span class="badge badge-secondary">Hidden</span>
        @endif
    </td>
    <td class="text-right text-nowrap">
        <a href="{{ url('admin/homestead/featured/edit/' . $entry->id) }}" class="btn btn-primary btn-sm">Edit</a>
        {!! Form::open(['url' => 'admin/homestead/featured/toggle/' . $entry->id, 'class' => 'd-inline']) !!}
            {!! Form::submit($entry->is_active ? 'Disable' : 'Enable', ['class' => 'btn btn-outline-secondary btn-sm']) !!}
        {!! Form::close() !!}
        {!! Form::open(['url' => 'admin/homestead/featured/delete/' . $entry->id, 'class' => 'd-inline', 'onsubmit' => 'return confirm("Remove this featured entry?")']) !!}
            {!! Form::submit('Remove', ['class' => 'btn btn-danger btn-sm']) !!}
        {!! Form::close() !!}
    </td>
</tr>
