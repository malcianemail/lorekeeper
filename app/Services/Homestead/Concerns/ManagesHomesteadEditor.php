<?php

namespace App\Services\Homestead\Concerns;

use DB;
use App\Services\Homestead\HomesteadConfig;
use App\Models\Homestead\RoomSave;
use App\Models\Homestead\RoomLayout;
use App\Models\Homestead\RoomPlacement;
use App\Models\User\UserItem;
use App\Models\Item\Item;
use App\Models\Character\CharacterSprite;

trait ManagesHomesteadEditor
{
    use ManagesHomesteadEditorSprites;

    /**
     * Placements dropped during the most recent save attempt.
     *
     * @var int
     */
    protected $lastDroppedPlacementCount = 0;

    /**
     * Cached placeable item IDs per room type for the current request.
     *
     * @var array<string, \Illuminate\Support\Collection>
     */
    protected $placeableItemIdsCache = [];

    /**
     * Get editor sidebar inventory groups for a space type.
     *
     * @param  string  $roomType
     * @return array
     */
    public function getEditorInventoryGroups($roomType)
    {
        return HomesteadConfig::inventoryGroups($roomType);
    }

    /**
     * Get editor UI settings for a space type.
     *
     * @param  string  $roomType
     * @return array
     */
    public function getEditorSettings($roomType)
    {
        return HomesteadConfig::editorSettings($roomType);
    }

    /**
     * Get canvas dimensions for the editor.
     *
     * @param  string  $roomType
     * @return array{width: int, height: int}
     */
    public function getEditorCanvasSize($roomType)
    {
        return HomesteadConfig::canvasSize($roomType);
    }

    /**
     * Build view data for the shared homestead editor.
     *
     * @param  \App\Models\User\User           $user
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return array
     */
    public function getEditorViewData($user, $room)
    {
        $room->load([
            'layout',
            'placements' => function ($query) {
                $query->select([
                    'id',
                    'room_save_id',
                    'item_id',
                    'character_sprite_id',
                    'x',
                    'y',
                    'width',
                    'height',
                    'z_index',
                ])->orderBy('z_index');
            },
        ]);

        $settings = $this->getEditorSettings($room->room_type);
        $canvas = $this->getEditorCanvasSize($room->room_type);
        $placedCounts = $this->getPlacedCountsForRoom($room);
        $inventory = $this->getEditorInventory($user, $room->room_type, $room, $placedCounts);
        $spriteInventory = $this->getEditorSpriteInventory($user, $room);
        $segment = HomesteadConfig::listSegment($room->room_type);
        $loadablePlacements = $this->filterLoadablePlacements($room->placements, $user, $room);
        $skippedPlacementCount = $room->placements->count() - $loadablePlacements->count();

        return [
            'room' => $room,
            'editor' => $settings,
            'exitUrl' => url('homestead/' . $segment),
            'saveUrl' => url('homestead/' . $segment . '/' . $room->id . '/editor'),
            'inventory' => $inventory,
            'spriteInventory' => $spriteInventory,
            'editorCatalog' => $this->buildEditorCatalog($inventory, $loadablePlacements, $room->room_type),
            'spriteCatalog' => $this->buildSpriteCatalogForRoom($spriteInventory, $loadablePlacements),
            'initialPlacements' => $this->formatPlacementsForEditor($loadablePlacements),
            'skippedPlacementCount' => $skippedPlacementCount,
            'initialLayout' => $this->getInitialLayoutForEditor($room),
            'surfaceLayoutFields' => HomesteadConfig::surfaceLayoutFields($room->room_type),
            'surfaceEditorNotes' => HomesteadConfig::surfaceEditorNotes($room->room_type),
            'canvasBackground' => HomesteadConfig::canvasBackgroundAttributes($room->room_type),
            'canvasWidth' => $canvas['width'],
            'canvasHeight' => $canvas['height'],
        ];
    }

    /**
     * Get grouped placeable inventory for the editor sidebar.
     *
     * @param  \App\Models\User\User           $user
     * @param  string                          $roomType
     * @param  \App\Models\Homestead\RoomSave  $room
     * @param  array|null                      $placedCounts
     * @return array
     */
    public function getEditorInventory($user, $roomType, $room, $placedCounts = null)
    {
        $placeableItemIds = $this->getPlaceableItemIds($roomType);
        $groups = $this->getEditorInventoryGroups($roomType);

        if ($placeableItemIds->isEmpty()) {
            return array_fill_keys(array_keys($groups), collect());
        }

        if ($placedCounts === null) {
            $placedCounts = $this->getPlacedCountsForRoom($room);
        }

        $quantities = UserItem::query()
            ->where('user_id', $user->id)
            ->where('count', '>', 0)
            ->whereExists(function ($query) use ($roomType) {
                Item::placeableInHomestead($roomType)
                    ->selectRaw('1')
                    ->whereColumn('items.id', 'user_items.item_id');
            })
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(count) as quantity')
            ->pluck('quantity', 'item_id');

        if ($quantities->isEmpty()) {
            return array_fill_keys(array_keys($groups), collect());
        }

        $items = Item::query()
            ->whereIn('id', $quantities->keys())
            ->select(['id', 'name', 'has_image', 'default_width', 'default_height', 'placement_type'])
            ->sortAlphabetical()
            ->get()
            ->keyBy('id');

        $entries = $quantities->map(function ($quantity, $itemId) use ($items, $placedCounts) {
            $item = $items->get($itemId);
            if (!$item) {
                return null;
            }

            $quantity = (int) $quantity;
            $placed = (int) ($placedCounts[$itemId] ?? 0);

            return (object) [
                'item' => $item,
                'quantity' => $quantity,
                'placed' => $placed,
                'available' => max(0, $quantity - $placed),
            ];
        })->filter()->sortBy(function ($entry) {
            return $entry->item->name;
        })->values();

        $grouped = [];
        foreach ($groups as $groupKey => $placementTypes) {
            $grouped[$groupKey] = $entries->filter(function ($entry) use ($placementTypes) {
                return in_array($entry->item->placement_type, $placementTypes, true);
            })->values();
        }

        return $grouped;
    }

    /**
     * Get saved placements for the editor.
     *
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return array
     */
    public function getPlacementsForEditor($room)
    {
        if (!$room->relationLoaded('placements')) {
            $room->load(['placements' => function ($query) {
                $query->orderBy('z_index');
            }]);
        }

        return $this->formatPlacementsForEditor($room->placements);
    }

    /**
     * Build catalog data including items already placed in the room.
     *
     * @param  array                                                          $inventory
     * @param  \App\Models\Homestead\RoomSave|\Illuminate\Support\Collection  $roomOrPlacements
     * @return array
     */
    public function getEditorCatalogForRoom($inventory, $roomOrPlacements)
    {
        $placements = $roomOrPlacements instanceof RoomSave
            ? $roomOrPlacements->placements
            : $roomOrPlacements;

        return $this->buildEditorCatalog($inventory, $placements, $roomOrPlacements instanceof RoomSave ? $roomOrPlacements->room_type : null);
    }

    /**
     * Get saved surface layout for the editor.
     *
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return array
     */
    public function getInitialLayoutForEditor($room)
    {
        $layout = $room->layout;
        if (!$layout) {
            return [];
        }

        $fields = array_values(array_unique(array_values(
            HomesteadConfig::surfaceLayoutFields($room->room_type)
        )));

        $result = [];
        foreach ($fields as $field) {
            if ($layout->{$field}) {
                $result[$field] = (int) $layout->{$field};
            }
        }

        return $result;
    }

    /**
     * Save homestead editor placements and surface layout.
     *
     * @param  array                             $data
     * @param  \App\Models\User\User             $user
     * @param  \App\Models\Homestead\RoomSave    $room
     * @return bool
     */
    public function saveEditorState($data, $user, $room)
    {
        DB::beginTransaction();

        try {
            $this->lastDroppedPlacementCount = 0;
            $this->assertUserOwnsSpace($user, $room);

            $inventory = $this->getEditorInventory($user, $room->room_type, $room);

            if (!$this->persistPlacements($data, $user, $room, $inventory)) {
                $messages = $this->errors()->getMessages()['error'] ?? ['Unable to save placements.'];
                throw new \Exception($messages[0]);
            }

            $this->persistLayoutSurfaces($data['layout'] ?? [], $user, $room, $inventory);

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * @deprecated Use saveEditorState() instead.
     */
    public function savePlacements($data, $user, $room)
    {
        return $this->saveEditorState($data, $user, $room);
    }

    /**
     * Build a single catalog entry for the editor client.
     *
     * @param  \App\Models\Item\Item  $item
     * @param  int                    $quantity
     * @return array
     */
    protected function buildCatalogEntry($item, $quantity)
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'imageUrl' => $item->imageUrl,
            'hasImage' => (bool) $item->has_image,
            'width' => (int) ($item->default_width ?: 64),
            'height' => (int) ($item->default_height ?: 64),
            'quantity' => (int) $quantity,
            'placementType' => $item->placement_type,
        ];
    }

    /**
     * Get placeable homestead item IDs for a room type (cached per request).
     *
     * @param  string  $roomType
     * @return \Illuminate\Support\Collection
     */
    protected function getPlaceableItemIds($roomType)
    {
        if (!isset($this->placeableItemIdsCache[$roomType])) {
            $this->placeableItemIdsCache[$roomType] = Item::placeableInHomestead($roomType)->pluck('id');
        }

        return $this->placeableItemIdsCache[$roomType];
    }

    /**
     * Get placed item counts keyed by item ID for a room.
     *
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return array<int, int>
     */
    protected function getPlacedCountsForRoom($room)
    {
        if ($room->relationLoaded('placements')) {
            return $room->placements->countBy('item_id')->all();
        }

        return RoomPlacement::query()
            ->where('room_save_id', $room->id)
            ->selectRaw('item_id, COUNT(*) as placed_count')
            ->groupBy('item_id')
            ->pluck('placed_count', 'item_id')
            ->all();
    }

    /**
     * Format placement models for the editor payload.
     *
     * @param  \Illuminate\Support\Collection  $placements
     * @return array
     */
    protected function formatPlacementsForEditor($placements)
    {
        return $placements->map(function ($placement) {
            $payload = [
                'x' => (float) $placement->x,
                'y' => (float) $placement->y,
                'width' => (float) $placement->width,
                'height' => (float) $placement->height,
                'z_index' => (int) $placement->z_index,
            ];

            if ($placement->character_sprite_id) {
                $payload['character_sprite_id'] = (int) $placement->character_sprite_id;
            } else {
                $payload['item_id'] = (int) $placement->item_id;
            }

            return $payload;
        })->values()->all();
    }

    public function getLastDroppedPlacementCount()
    {
        return (int) $this->lastDroppedPlacementCount;
    }

    /**
     * Build the editor item catalog from inventory and current placements.
     *
     * @param  array                           $inventory
     * @param  \Illuminate\Support\Collection  $placements
     * @param  string|null                     $roomType
     * @return array
     */
    protected function buildEditorCatalog($inventory, $placements, $roomType = null)
    {
        $catalog = [];

        foreach ($inventory as $entries) {
            foreach ($entries as $entry) {
                $catalog[$entry->item->id] = $this->buildCatalogEntry($entry->item, $entry->quantity);
            }
        }

        if (!$roomType) {
            return $catalog;
        }

        $missingItemIds = $placements->pluck('item_id')->unique()->filter(function ($itemId) use ($catalog) {
            return $itemId && !isset($catalog[$itemId]);
        });

        if ($missingItemIds->isEmpty()) {
            return $catalog;
        }

        $placeableIds = array_flip($this->getPlaceableItemIds($roomType)->all());

        foreach (Item::whereIn('id', $missingItemIds)->select(['id', 'name', 'has_image', 'default_width', 'default_height', 'placement_type'])->get() as $item) {
            if (!isset($placeableIds[$item->id])) {
                continue;
            }

            $catalog[$item->id] = $this->buildCatalogEntry($item, 0);
        }

        return $catalog;
    }

    /**
     * Persist furniture placements for the editor.
     *
     * @param  array                             $data
     * @param  \App\Models\User\User             $user
     * @param  \App\Models\Homestead\RoomSave    $room
     * @param  array|null                        $inventory
     * @return bool
     */
    protected function persistPlacements($data, $user, $room, $inventory = null)
    {
        try {
            $this->assertUserOwnsSpace($user, $room);

            $placements = $data['placements'] ?? [];
            if (!is_array($placements)) {
                throw new \Exception('Invalid placement data.');
            }

            $settings = $this->getEditorSettings($room->room_type);
            $inventory = $inventory ?? $this->getEditorInventory($user, $room->room_type, $room);
            $placeableGroup = $settings['placeable_group'] ?? 'furniture';
            $furnitureTypes = array_flip(HomesteadConfig::inventoryGroups($room->room_type)[$placeableGroup] ?? []);
            $placeableItemIds = array_flip($this->getPlaceableItemIds($room->room_type)->all());
            $ownedQuantities = [];
            foreach ($inventory[$placeableGroup] ?? [] as $entry) {
                $ownedQuantities[$entry->item->id] = $entry->quantity;
            }

            $canvas = $this->getEditorCanvasSize($room->room_type);
            $canvasWidth = $canvas['width'];
            $canvasHeight = $canvas['height'];
            $itemCounts = [];
            $normalizedPlacements = [];
            $normalizedSpritePlacements = [];
            $requestedItemIds = [];

            foreach ($placements as $placement) {
                if (!is_array($placement)) {
                    throw new \Exception('Invalid placement data.');
                }

                if (!empty($placement['character_sprite_id'])) {
                    continue;
                }

                if (!isset($placement['item_id'], $placement['x'], $placement['y'], $placement['z_index'])) {
                    throw new \Exception('Invalid placement data.');
                }

                $requestedItemIds[] = (int) $placement['item_id'];
            }

            $normalizedSpritePlacements = $this->normalizeSpritePlacements(
                $placements,
                $user,
                $room,
                $settings,
                $canvas
            );

            $itemsById = $requestedItemIds
                ? Item::whereIn('id', array_unique($requestedItemIds))
                    ->select(['id', 'is_homestead_item', 'placement_type', 'default_width', 'default_height'])
                    ->get()
                    ->keyBy('id')
                : collect();

            foreach ($placements as $placement) {
                if (!empty($placement['character_sprite_id'])) {
                    continue;
                }

                $itemId = (int) $placement['item_id'];
                if (!isset($placeableItemIds[$itemId])) {
                    $this->lastDroppedPlacementCount++;
                    continue;
                }

                $item = $itemsById->get($itemId);
                if (!$item || !$item->is_homestead_item) {
                    throw new \Exception($settings['invalid_item_message']);
                }

                if (!isset($furnitureTypes[$item->placement_type])) {
                    throw new \Exception($settings['invalid_item_message']);
                }

                $width = (int) ($item->default_width ?: 64);
                $height = (int) ($item->default_height ?: 64);
                $x = (float) $placement['x'];
                $y = (float) $placement['y'];
                $zIndex = (int) $placement['z_index'];

                if ($width <= 0 || $height <= 0) {
                    throw new \Exception('Invalid placement dimensions.');
                }

                if ($x < 0 || $y < 0 || ($x + $width) > $canvasWidth || ($y + $height) > $canvasHeight) {
                    throw new \Exception($settings['boundary_message']);
                }

                if ($zIndex < 1 || $zIndex > 9999) {
                    throw new \Exception('Invalid placement data.');
                }

                $itemCounts[$itemId] = ($itemCounts[$itemId] ?? 0) + 1;
                $normalizedPlacements[] = [
                    'item_id' => $itemId,
                    'x' => $x,
                    'y' => $y,
                    'width' => $width,
                    'height' => $height,
                    'z_index' => $zIndex,
                ];
            }

            foreach ($itemCounts as $itemId => $count) {
                if ($count > ($ownedQuantities[$itemId] ?? 0)) {
                    throw new \Exception('You do not own enough of one or more placed items.');
                }
            }

            RoomPlacement::where('room_save_id', $room->id)->delete();

            $rows = [];
            $timestamp = now();

            foreach ($normalizedPlacements as $placement) {
                $rows[] = [
                    'room_save_id' => $room->id,
                    'item_id' => $placement['item_id'],
                    'character_sprite_id' => null,
                    'x' => $placement['x'],
                    'y' => $placement['y'],
                    'width' => $placement['width'],
                    'height' => $placement['height'],
                    'z_index' => $placement['z_index'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            foreach ($normalizedSpritePlacements as $placement) {
                $rows[] = [
                    'room_save_id' => $room->id,
                    'item_id' => null,
                    'character_sprite_id' => $placement['character_sprite_id'],
                    'x' => $placement['x'],
                    'y' => $placement['y'],
                    'width' => $placement['width'],
                    'height' => $placement['height'],
                    'z_index' => $placement['z_index'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            if ($rows) {
                foreach (array_chunk($rows, 250) as $chunk) {
                    RoomPlacement::insert($chunk);
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return false;
    }

    /**
     * Persist surface selections on the room layout.
     *
     * @param  array                             $layoutData
     * @param  \App\Models\User\User             $user
     * @param  \App\Models\Homestead\RoomSave    $room
     * @param  array|null                        $inventory
     * @return void
     */
    protected function persistLayoutSurfaces($layoutData, $user, $room, $inventory = null)
    {
        $this->assertUserOwnsSpace($user, $room);

        if (!is_array($layoutData)) {
            throw new \Exception('Invalid surface layout data.');
        }

        $layout = $room->layout;
        if (!$layout) {
            $layout = RoomLayout::create(['room_save_id' => $room->id]);
            $room->setRelation('layout', $layout);
        }

        $fieldMap = HomesteadConfig::surfaceLayoutFields($room->room_type);
        $allowedFields = array_values(array_unique(array_values($fieldMap)));

        $unknownLayoutKeys = array_diff(array_keys($layoutData), $allowedFields);
        if (!empty($unknownLayoutKeys)) {
            throw new \Exception('Invalid surface layout data.');
        }

        $inventory = $inventory ?? $this->getEditorInventory($user, $room->room_type, $room);
        $ownedSurfaceItemIds = [];

        foreach ($inventory['surfaces'] ?? [] as $entry) {
            $ownedSurfaceItemIds[$entry->item->id] = $entry->item->placement_type;
        }

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $layoutData)) {
                continue;
            }

            $itemId = $layoutData[$field];
            if ($itemId === null || $itemId === '' || $itemId === 0 || $itemId === '0') {
                $layout->{$field} = null;
                continue;
            }

            if (!is_numeric($itemId)) {
                throw new \Exception('Invalid surface layout data.');
            }

            $itemId = (int) $itemId;
            if (!isset($ownedSurfaceItemIds[$itemId])) {
                throw new \Exception('One or more selected surface items are unavailable.');
            }

            $placementType = $ownedSurfaceItemIds[$itemId];
            if (($fieldMap[$placementType] ?? null) !== $field) {
                throw new \Exception('One or more selected surface items cannot be used in this slot.');
            }

            $layout->{$field} = $itemId;
        }

        $layout->save();
    }

    /**
     * Keep only placements that can be rendered and saved in the editor.
     *
     * @param  \Illuminate\Support\Collection  $placements
     * @param  \App\Models\User\User           $user
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return \Illuminate\Support\Collection
     */
    protected function filterLoadablePlacements($placements, $user, $room)
    {
        $roomType = $room->room_type;
        $placeableItemIds = array_flip($this->getPlaceableItemIds($roomType)->all());
        $settings = $this->getEditorSettings($roomType);
        $placeableGroup = $settings['placeable_group'] ?? 'furniture';
        $furnitureTypes = array_flip(HomesteadConfig::inventoryGroups($roomType)[$placeableGroup] ?? []);

        $itemIds = $placements->pluck('item_id')->filter()->unique()->all();
        $items = $itemIds
            ? Item::whereIn('id', $itemIds)->select(['id', 'placement_type'])->get()->keyBy('id')
            : collect();

        $spriteIds = $placements->pluck('character_sprite_id')->filter()->unique()->all();
        $ownedSpriteIds = [];

        if ($spriteIds) {
            $ownedSpriteIds = CharacterSprite::query()
                ->select('character_sprites.id')
                ->join('characters', 'characters.id', '=', 'character_sprites.character_id')
                ->where('character_sprites.has_image', 1)
                ->where('characters.user_id', $user->id)
                ->where('characters.is_myo_slot', 0)
                ->whereIn('character_sprites.id', $spriteIds)
                ->pluck('character_sprites.id')
                ->flip()
                ->all();
        }

        return $placements->filter(function ($placement) use ($placeableItemIds, $furnitureTypes, $items, $ownedSpriteIds) {
            if ($placement->character_sprite_id) {
                return isset($ownedSpriteIds[$placement->character_sprite_id]);
            }

            if (!$placement->item_id) {
                return false;
            }

            $item = $items->get($placement->item_id);
            if (!$item) {
                return false;
            }

            return isset($placeableItemIds[$placement->item_id])
                && isset($furnitureTypes[$item->placement_type]);
        })->values();
    }
}
