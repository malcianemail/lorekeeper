<div class="homestead-editor-inventory-tabs">
    <ul class="nav nav-pills nav-fill homestead-editor-inventory-nav mb-2" role="tablist">
        <li class="nav-item">
            <a class="nav-link active py-1 px-2 small" data-toggle="tab" href="#editorInventoryFurniture" role="tab">
                <i class="fas fa-couch"></i> Furniture
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-2 small" data-toggle="tab" href="#editorInventorySprites" role="tab">
                <i class="fas fa-user"></i> Sprites
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-2 small" data-toggle="tab" href="#editorInventorySurfaces" role="tab">
                <i class="fas fa-palette"></i> Surfaces
            </a>
        </li>
    </ul>

    <div class="tab-content homestead-editor-inventory-tab-content">
        @foreach(['furniture' => 'Furniture', 'surfaces' => 'Surfaces'] as $groupKey => $groupLabel)
            <div class="tab-pane fade {{ $groupKey === 'furniture' ? 'show active' : '' }}" id="editorInventory{{ ucfirst($groupKey) }}" role="tabpanel">
                @if($groupKey === 'surfaces' && !empty($surfaceEditorNotes))
                    <p class="small text-muted mb-2">{{ $surfaceEditorNotes }}</p>
                @endif
                @if(count($inventory[$groupKey]))
                    <div class="homestead-editor-inventory-grid">
                        @foreach($inventory[$groupKey] as $entry)
                            @php
                                $layoutField = ($groupKey === 'surfaces' && isset($surfaceLayoutFields))
                                    ? ($surfaceLayoutFields[$entry->item->placement_type] ?? null)
                                    : null;
                            @endphp
                            <div class="homestead-editor-inventory-item {{ ($placeableGroup ?? 'furniture') === $groupKey ? 'is-placeable' : '' }} {{ $layoutField ? 'is-surface-selectable' : '' }} {{ ($placeableGroup ?? 'furniture') === $groupKey && $entry->available <= 0 ? 'is-unavailable' : '' }}"
                                 data-item-id="{{ $entry->item->id }}"
                                 data-placement-type="{{ $entry->item->placement_type }}"
                                 data-layout-field="{{ $layoutField }}"
                                 data-item-width="{{ $entry->item->default_width ?: 64 }}"
                                 data-item-height="{{ $entry->item->default_height ?: 64 }}"
                                 title="{{ $entry->item->name }}{{ ($placeableGroup ?? 'furniture') === $groupKey && $entry->available <= 0 ? ' (all placed)' : '' }}">
                                <div class="homestead-editor-inventory-item-image">
                                    @if($entry->item->has_image)
                                        <img src="{{ $entry->item->imageUrl }}" alt="{{ $entry->item->name }}" loading="lazy" decoding="async">
                                    @else
                                        <i class="fas fa-cube text-muted"></i>
                                    @endif
                                </div>
                                <div class="homestead-editor-inventory-item-name">{{ $entry->item->name }}</div>
                                <div class="homestead-editor-inventory-item-qty">
                                    @if($entry->placed > 0)
                                        {{ $entry->available }} / {{ $entry->quantity }}
                                    @else
                                        x{{ $entry->quantity }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="homestead-editor-inventory-empty">
                        <p class="small text-muted mb-2">No {{ strtolower($groupLabel) }} items in your inventory.</p>
                        @if(!empty($emptyInventoryHint))
                            <p class="small text-muted mb-0">{{ $emptyInventoryHint }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        <div class="tab-pane fade" id="editorInventorySprites" role="tabpanel">
            @include('homestead._editor_sprites', ['spriteInventory' => $spriteInventory ?? collect()])
        </div>
    </div>
</div>
