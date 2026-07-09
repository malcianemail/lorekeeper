@extends('homestead.editor_layout')

@section('editor-title') {{ $room->name }} @endsection

@section('editor-content')
<div class="homestead-editor" id="homesteadEditorApp">
    <div class="homestead-editor-toolbar d-flex align-items-center justify-content-between flex-wrap">
        @if(!empty($skippedPlacementCount))
            <div class="alert alert-warning w-100 mb-2 py-2 small">
                {{ $skippedPlacementCount }} saved placement{{ $skippedPlacementCount === 1 ? '' : 's' }} could not be loaded because the item or sprite is no longer available. Saving will remove them from this space.
            </div>
        @endif
        <div class="homestead-editor-toolbar-title mb-2 mb-md-0">
            <h1 class="h4 mb-0">{{ $room->name }}</h1>
            <small class="text-muted d-block">{{ $editor['label'] }}</small>
            <small class="text-muted d-none d-md-block">{{ $editor['help_text'] }}</small>
            <span class="badge badge-warning homestead-editor-unsaved-badge d-none" id="homesteadEditorUnsavedBadge">Unsaved changes</span>
        </div>
        <div class="homestead-editor-toolbar-actions d-flex flex-wrap align-items-center">
            <div class="homestead-editor-selection-controls btn-group btn-group-sm mr-2 mb-2 d-none" id="homesteadEditorControls">
                <button type="button" class="btn btn-outline-secondary" data-editor-action="backward" title="Move Backward">
                    <i class="fas fa-arrow-down"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-editor-action="forward" title="Move Forward">
                    <i class="fas fa-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-outline-danger" data-editor-action="remove" title="Remove item">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none mr-2 mb-2" id="toggleInventoryButton" title="Toggle inventory panel">
                <i class="fas fa-box-open"></i> Inventory
            </button>
            <a href="{{ $exitUrl }}" class="btn btn-outline-secondary btn-sm mr-2 mb-2" id="homesteadEditorExitButton" title="Return to {{ $editor['list_segment'] }} list">
                <i class="fas fa-times"></i> Exit
            </a>
            {!! Form::open(['url' => $saveUrl, 'id' => 'homesteadEditorSaveForm', 'class' => 'd-inline mb-2']) !!}
                <input type="hidden" name="placements" id="homesteadEditorPlacementsInput" value="">
                <input type="hidden" name="layout" id="homesteadEditorLayoutInput" value="">
                {!! Form::submit('Save', ['class' => 'btn btn-primary btn-sm', 'id' => 'homesteadEditorSaveButton']) !!}
            {!! Form::close() !!}
        </div>
    </div>

    <div class="homestead-editor-body row no-gutters">
        <div class="col-lg-9 homestead-editor-canvas-col">
            <div class="homestead-editor-canvas" id="homesteadEditorCanvasWrap">
                <div class="homestead-editor-canvas-loading" id="homesteadEditorCanvasLoading">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <span class="sr-only">Loading editor...</span>
                </div>
                <div class="homestead-editor-canvas-container" id="homesteadEditorCanvasContainer">
                    <div class="homestead-editor-canvas-stage" id="homesteadEditorCanvasStage">
                        <div class="{{ $canvasBackground['class'] }}"@if(!empty($canvasBackground['style'])) style="{{ $canvasBackground['style'] }}"@endif></div>
                        <div class="homestead-editor-canvas-wall-surface" id="homesteadEditorWallSurface"></div>
                        <div class="homestead-editor-canvas-floor-surface" id="homesteadEditorFloorSurface"></div>
                        <div class="homestead-editor-canvas-items" id="homesteadEditorCanvasItems"></div>
                    </div>
                    <div class="homestead-editor-canvas-placeholder d-none" id="homesteadEditorCanvasPlaceholder">
                        <i class="fas fa-couch fa-2x text-muted mb-2"></i>
                        <p class="small text-muted mb-0">Select furniture or sprites from the inventory, then click or drag them onto the canvas.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 homestead-editor-inventory-col" id="editorInventoryPanel">
            <div class="homestead-editor-inventory">
                <div class="homestead-editor-inventory-header d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0 text-uppercase">Inventory</h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" id="closeInventoryButton" title="Close inventory">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="homestead-editor-inventory-body" id="homesteadEditorInventory">
                    @include('homestead._editor_inventory', [
                        'placeableGroup' => $editor['placeable_group'] ?? 'furniture',
                        'surfaceLayoutFields' => $surfaceLayoutFields,
                        'surfaceEditorNotes' => $surfaceEditorNotes ?? null,
                        'emptyInventoryHint' => $editor['empty_inventory_hint'] ?? null,
                        'spriteInventory' => $spriteInventory,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('editor-scripts')
<script src="{{ asset('js/homestead-room-editor.js') }}?v={{ filemtime(public_path('js/homestead-room-editor.js')) }}"></script>
<script>
    $(document).ready(function() {
        $('body').addClass('homestead-editor-active');
        $('#sidebar').hide();
        $('.site-mobile-header').hide();
        $('.main-content').removeClass('col-lg-8').addClass('col-lg-10 offset-lg-1');
        $('.site-footer').hide();

        var editor = new HomesteadRoomEditor({
            canvasWidth: {{ $canvasWidth }},
            canvasHeight: {{ $canvasHeight }},
            catalog: @json($editorCatalog),
            spriteCatalog: @json($spriteCatalog),
            initialPlacements: @json($initialPlacements),
            initialLayout: @json($initialLayout),
            surfaceCanvasLayers: @json(config('lorekeeper.homestead.surface_canvas_layers', [])),
            canvasWrapSelector: '#homesteadEditorCanvasWrap',
            canvasContainerSelector: '#homesteadEditorCanvasContainer',
            canvasStageSelector: '#homesteadEditorCanvasStage',
            canvasItemsSelector: '#homesteadEditorCanvasItems',
            canvasPlaceholderSelector: '#homesteadEditorCanvasPlaceholder',
            canvasLoadingSelector: '#homesteadEditorCanvasLoading',
            wallSurfaceSelector: '#homesteadEditorWallSurface',
            floorSurfaceSelector: '#homesteadEditorFloorSurface',
            controlsSelector: '#homesteadEditorControls',
            inventorySelector: '#homesteadEditorInventory',
        });

        window.homesteadEditor = editor;

        var $unsavedBadge = $('#homesteadEditorUnsavedBadge');
        var $saveButton = $('#homesteadEditorSaveButton');
        var $saveForm = $('#homesteadEditorSaveForm');
        var $exitButton = $('#homesteadEditorExitButton');

        $(document).on('homesteadEditor:dirty', function() {
            $unsavedBadge.removeClass('d-none');
        });

        $(document).on('homesteadEditor:clean', function() {
            $unsavedBadge.addClass('d-none');
        });

        $saveForm.on('submit', function() {
            $('#homesteadEditorPlacementsInput').val(JSON.stringify(editor.getPlacementsPayload()));
            $('#homesteadEditorLayoutInput').val(JSON.stringify(editor.getLayoutPayload()));

            if ($saveButton.prop('disabled')) {
                return false;
            }

            $saveButton.prop('disabled', true);
            $saveButton.data('original-html', $saveButton.val());
            $saveButton.val('Saving...');
        });

        $exitButton.on('click', function(e) {
            if (!editor.isDirty) {
                return;
            }

            if (!window.confirm('You have unsaved changes. Leave without saving?')) {
                e.preventDefault();
            }
        });

        $(window).on('beforeunload.homesteadEditor', function(e) {
            if (!editor.isDirty) {
                return;
            }

            e.preventDefault();
            e.returnValue = '';
        });

        var $panel = $('#editorInventoryPanel');
        var $toggle = $('#toggleInventoryButton');
        var $close = $('#closeInventoryButton');

        function closeInventoryPanel() {
            $panel.removeClass('is-open');
            $toggle.removeClass('active');
        }

        $toggle.on('click', function() {
            $panel.toggleClass('is-open');
            $(this).toggleClass('active');
        });

        $close.on('click', closeInventoryPanel);

        $(document).on('click', function(e) {
            if ($(window).width() >= 992) return;
            if (!$panel.hasClass('is-open')) return;
            if ($panel.is(e.target) || $panel.has(e.target).length) return;
            if ($toggle.is(e.target) || $toggle.has(e.target).length) return;
            closeInventoryPanel();
        });
    });
</script>
@endsection
