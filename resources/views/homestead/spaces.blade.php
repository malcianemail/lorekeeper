@extends('homestead.layout')

@section('homestead-title') {{ $labels['title'] }} @endsection

@section('homestead-content')
@php($segment = $labels['segment'])
{!! breadcrumbs(['Homestead' => 'homestead/'.$segment, $labels['title'] => 'homestead/'.$segment]) !!}

<div class="homestead-spaces-header clearfix mb-3">
    <h1 class="float-left mb-0">{{ $labels['title'] }}</h1>
    <div class="float-right homestead-spaces-actions mt-2 mt-md-0">
        @if($slots['can_create'])
            <a href="#" class="btn btn-primary create-space-button"><i class="fas fa-plus"></i> {{ $labels['create_action'] }}</a>
        @else
            <button type="button" class="btn btn-primary" disabled title="{{ ucfirst($labels['singular']) }} slot limit reached"><i class="fas fa-plus"></i> {{ $labels['create_action'] }}</button>
        @endif
    </div>
</div>

<p class="mb-3">
    {{ $labels['description'] }}
    @if($slots['unlimited'])
        You have <strong>{{ $slots['used'] }}</strong> {{ $labels['singular'] }}(s). <span class="text-muted">({{ \App\Services\Homestead\HomesteadConfig::unlimitedSlotsLabel() }})</span>
    @else
        You are using <strong>{{ $slots['used'] }}</strong> of <strong>{{ $slots['max'] }}</strong> {{ $labels['singular'] }} slots.
    @endif
</p>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 pl-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(!$slots['can_create'])
    <div class="alert alert-warning">
        {{ $labels['slot_limit_message'] }}
    </div>
@endif

@if(count($spaces))
    <div class="table-responsive mb-2">
        <table class="table table-sm table-hover homestead-spaces-table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="d-none d-md-table-cell">Created</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($spaces as $space)
                    <tr>
                        <td>
                            <strong>{{ $space->name }}</strong>
                            <div class="d-md-none small text-muted">{!! pretty_date($space->created_at) !!}</div>
                        </td>
                        <td class="d-none d-md-table-cell">{!! pretty_date($space->created_at) !!}</td>
                        <td class="text-right homestead-spaces-row-actions">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ $space->editor_url }}" class="btn btn-outline-success" title="Open {{ $labels['singular'] }} editor">
                                    <i class="fas fa-paint-brush d-md-none"></i><span class="d-none d-md-inline">Editor</span>
                                </a>
                                <a href="#" class="btn btn-outline-primary edit-space-button" data-id="{{ $space->id }}" title="Rename {{ $labels['singular'] }}">
                                    <i class="fas fa-edit d-md-none"></i><span class="d-none d-md-inline">Edit</span>
                                </a>
                                <a href="#" class="btn btn-outline-danger delete-space-button" data-id="{{ $space->id }}" title="Delete {{ $labels['singular'] }}">
                                    <i class="fas fa-trash d-md-none"></i><span class="d-none d-md-inline">Delete</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="text-center mb-3 small text-muted">
        {{ count($spaces) }} {{ $labels['singular'] }}{{ count($spaces) === 1 ? '' : 's' }}.
    </div>
@else
    <div class="homestead-spaces-empty text-center py-4 mb-3">
        <p class="h5 text-muted mb-2">{{ $labels['empty_message'] }}</p>
        <p class="text-muted mb-3">{{ $labels['empty_hint'] }}</p>
        @if($slots['can_create'])
            <a href="#" class="btn btn-primary create-space-button"><i class="fas fa-plus"></i> {{ $labels['create_action'] }}</a>
        @endif
    </div>
@endif

@endsection

@section('scripts')
@parent
<script>
    $(document).ready(function() {
        var baseUrl = "{{ url('homestead/'.$segment) }}";

        function openSpaceModal(url, title) {
            loadModal(url, title);
        }

        $('.create-space-button').on('click', function(e) {
            e.preventDefault();
            openSpaceModal(baseUrl + '/create', @json($labels['create_action']));
        });

        $('.edit-space-button').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            openSpaceModal(baseUrl + '/edit/' + $this.data('id'), @json($labels['edit_action']));
        });

        $('.delete-space-button').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            openSpaceModal(baseUrl + '/delete/' + $this.data('id'), @json($labels['delete_action']));
        });

        $(document).on('submit', '#modal .homestead-space-form', function() {
            var $form = $(this);
            var $btn = $form.find('[type=submit]');
            if ($btn.prop('disabled')) {
                return false;
            }
            $btn.prop('disabled', true);
            $btn.data('original-html', $btn.html());
            $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + $.trim($btn.text()));
        });

        @if($errors->any() && old('name'))
            openSpaceModal(
                baseUrl + '/{{ old('room_id') ? 'edit/'.old('room_id') : 'create' }}',
                @json(old('room_id') ? $labels['edit_action'] : $labels['create_action'])
            );
        @endif
    });
</script>
@endsection
