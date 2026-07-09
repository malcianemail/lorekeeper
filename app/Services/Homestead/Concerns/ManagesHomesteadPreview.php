<?php

namespace App\Services\Homestead\Concerns;

use App\Services\Homestead\HomesteadConfig;
use App\Models\Homestead\RoomSave;
use App\Models\Item\Item;
use App\Models\Character\CharacterSprite;
use Illuminate\Support\Collection;

trait ManagesHomesteadPreview
{
    /**
     * Build view data for a read-only homestead space preview.
     *
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return array
     */
    public function getPreviewViewData($room)
    {
        $previews = $this->getBatchPreviewViewData(collect([$room]));

        return $previews[$room->id] ?? [];
    }

    /**
     * Build preview payloads for multiple rooms in batched queries.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Support\Enumerable  $rooms
     * @return array<int, array>
     */
    public function getBatchPreviewViewData($rooms)
    {
        $rooms = $rooms instanceof Collection ? $rooms : collect($rooms);
        $rooms = $rooms->filter()->unique('id')->values();

        if ($rooms->isEmpty()) {
            return [];
        }

        $loadedRooms = RoomSave::query()
            ->with([
                'layout',
                'placements' => function ($query) {
                    $query->orderBy('z_index');
                },
            ])
            ->whereIn('id', $rooms->pluck('id')->all())
            ->get()
            ->keyBy('id');

        $itemIds = collect();
        $spriteIds = collect();

        foreach ($loadedRooms as $room) {
            foreach ($room->placements as $placement) {
                if ($placement->item_id) {
                    $itemIds->push($placement->item_id);
                }
                if ($placement->character_sprite_id) {
                    $spriteIds->push($placement->character_sprite_id);
                }
            }

            $layout = $room->layout;
            if ($layout) {
                foreach (['wallpaper_item_id', 'exterior_wall_item_id', 'flooring_item_id', 'roof_item_id'] as $field) {
                    if ($layout->{$field}) {
                        $itemIds->push($layout->{$field});
                    }
                }
            }
        }

        $items = $itemIds->isEmpty()
            ? collect()
            : Item::query()
                ->whereIn('id', $itemIds->unique()->all())
                ->select(['id', 'name', 'has_image', 'default_width', 'default_height', 'placement_type'])
                ->get()
                ->keyBy('id');

        $sprites = $spriteIds->isEmpty()
            ? collect()
            : CharacterSprite::query()
                ->whereIn('id', $spriteIds->unique()->all())
                ->with(['character:id,slug,name,is_myo_slot'])
                ->get()
                ->keyBy('id');

        $previews = [];

        foreach ($loadedRooms as $room) {
            $canvas = $this->getEditorCanvasSize($room->room_type);
            $placements = $room->placements;

            $previews[$room->id] = [
                'room' => $room,
                'editorCatalog' => $this->buildPreviewCatalogFromCollections($placements, $room, $items),
                'spriteCatalog' => $this->buildSpriteCatalogFromCollections($placements, $sprites),
                'initialPlacements' => $this->formatPlacementsForEditor($placements),
                'initialLayout' => $this->getInitialLayoutForEditor($room),
                'canvasBackground' => HomesteadConfig::canvasBackgroundAttributes($room->room_type),
                'canvasWidth' => $canvas['width'],
                'canvasHeight' => $canvas['height'],
            ];
        }

        return $previews;
    }

    /**
     * Build item catalog data for preview rendering from placements and layout.
     *
     * @param  \Illuminate\Support\Collection  $placements
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return array
     */
    protected function buildPreviewCatalog($placements, $room)
    {
        $itemIds = $placements->pluck('item_id')->filter();

        $layout = $room->layout;
        if ($layout) {
            foreach (['wallpaper_item_id', 'exterior_wall_item_id', 'flooring_item_id', 'roof_item_id'] as $field) {
                if ($layout->{$field}) {
                    $itemIds->push($layout->{$field});
                }
            }
        }

        $items = $itemIds->isEmpty()
            ? collect()
            : Item::query()
                ->whereIn('id', $itemIds->unique()->filter()->all())
                ->select(['id', 'name', 'has_image', 'default_width', 'default_height', 'placement_type'])
                ->get()
                ->keyBy('id');

        return $this->buildPreviewCatalogFromCollections($placements, $room, $items);
    }

    /**
     * Build preview catalog entries from preloaded item models.
     *
     * @param  \Illuminate\Support\Collection  $placements
     * @param  \App\Models\Homestead\RoomSave  $room
     * @param  \Illuminate\Support\Collection  $items
     * @return array
     */
    protected function buildPreviewCatalogFromCollections($placements, $room, $items)
    {
        $itemIds = $placements->pluck('item_id')->filter();

        $layout = $room->layout;
        if ($layout) {
            foreach (['wallpaper_item_id', 'exterior_wall_item_id', 'flooring_item_id', 'roof_item_id'] as $field) {
                if ($layout->{$field}) {
                    $itemIds->push($layout->{$field});
                }
            }
        }

        $catalog = [];
        foreach ($itemIds->unique()->filter() as $itemId) {
            $item = $items->get($itemId);
            if ($item) {
                $catalog[$item->id] = $this->buildCatalogEntry($item, 1);
            }
        }

        return $catalog;
    }

    /**
     * Build sprite catalog entries from preloaded sprite models.
     *
     * @param  \Illuminate\Support\Collection  $placements
     * @param  \Illuminate\Support\Collection  $sprites
     * @return array
     */
    protected function buildSpriteCatalogFromCollections($placements, $sprites)
    {
        $catalog = [];
        $size = HomesteadConfig::defaultSpriteSize();

        foreach ($placements->pluck('character_sprite_id')->filter()->unique() as $spriteId) {
            $sprite = $sprites->get($spriteId);
            if (!$sprite || !$sprite->imageUrl) {
                continue;
            }

            $catalog[$sprite->id] = [
                'id' => $sprite->id,
                'characterId' => $sprite->character_id,
                'characterName' => $sprite->character ? $sprite->character->fullName : 'Character',
                'name' => $sprite->displayName,
                'imageUrl' => $sprite->imageUrl,
                'hasImage' => true,
                'width' => $size['width'],
                'height' => $size['height'],
                'quantity' => 1,
            ];
        }

        return $catalog;
    }
}
