@if(count($spriteInventory))
    <div class="homestead-editor-inventory-grid">
        @foreach($spriteInventory as $entry)
            <div class="homestead-editor-inventory-item is-sprite-placeable {{ $entry->available <= 0 ? 'is-unavailable' : '' }}"
                 data-sprite-id="{{ $entry->sprite->id }}"
                 data-item-width="{{ \App\Services\Homestead\HomesteadConfig::defaultSpriteSize()['width'] }}"
                 data-item-height="{{ \App\Services\Homestead\HomesteadConfig::defaultSpriteSize()['height'] }}"
                 title="{{ $entry->sprite->character->fullName }} — {{ $entry->sprite->displayName }}{{ $entry->available <= 0 ? ' (placed)' : '' }}">
                <div class="homestead-editor-inventory-item-image">
                    <img src="{{ $entry->sprite->imageUrl }}" alt="{{ $entry->sprite->displayName }}" loading="lazy" decoding="async">
                </div>
                <div class="homestead-editor-inventory-item-name">{{ $entry->sprite->displayName }}</div>
                <div class="homestead-editor-inventory-item-qty small text-muted">{{ $entry->sprite->character->slug }}</div>
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
        <p class="small text-muted mb-2">{{ \App\Services\Homestead\HomesteadConfig::editorSpritesLabel('empty_message') }}</p>
        <p class="small text-muted mb-0">{{ \App\Services\Homestead\HomesteadConfig::editorSpritesLabel('empty_hint') }}</p>
    </div>
@endif
