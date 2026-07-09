@php
    $previewId = $previewId ?? ('homesteadPreview' . uniqid());
    $compact = !empty($compact);
@endphp

<div class="homestead-room-preview {{ $compact ? 'homestead-room-preview-compact' : '' }}" id="{{ $previewId }}">
    <div class="homestead-room-preview-stage-wrap">
        <div class="homestead-editor-canvas-stage homestead-room-preview-stage" data-preview-stage>
            <div class="{{ $preview['canvasBackground']['class'] }}"@if(!empty($preview['canvasBackground']['style'])) style="{{ $preview['canvasBackground']['style'] }}"@endif></div>
            <div class="homestead-editor-canvas-wall-surface" data-preview-wall></div>
            <div class="homestead-editor-canvas-floor-surface" data-preview-floor></div>
            <div class="homestead-editor-canvas-items" data-preview-items></div>
        </div>
    </div>
</div>

<script>
    (function() {
        var mount = function() {
            if (!window.HomesteadRoomPreview) return;
            HomesteadRoomPreview.mountAll('#{{ $previewId }}');
        };

        $('#{{ $previewId }}').data('preview-options', {
            canvasWidth: {{ $preview['canvasWidth'] }},
            canvasHeight: {{ $preview['canvasHeight'] }},
            catalog: @json($preview['editorCatalog']),
            spriteCatalog: @json($preview['spriteCatalog']),
            initialPlacements: @json($preview['initialPlacements']),
            initialLayout: @json($preview['initialLayout']),
            surfaceCanvasLayers: @json(config('lorekeeper.homestead.surface_canvas_layers', [])),
            maxWidth: {{ $compact ? 320 : 700 }},
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', mount);
        } else {
            mount();
        }
    })();
</script>
