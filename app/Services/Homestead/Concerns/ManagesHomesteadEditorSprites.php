<?php

namespace App\Services\Homestead\Concerns;

use App\Models\Character\CharacterSprite;
use App\Models\Homestead\RoomPlacement;
use App\Models\Homestead\RoomSave;
use App\Services\Homestead\HomesteadConfig;

trait ManagesHomesteadEditorSprites
{
    /**
     * Get available character sprites for the editor sidebar.
     *
     * @param  \App\Models\User\User           $user
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return \Illuminate\Support\Collection
     */
    public function getEditorSpriteInventory($user, $room)
    {
        $placedCounts = $this->getPlacedSpriteCountsForRoom($room);

        return CharacterSprite::query()
            ->select('character_sprites.*')
            ->join('characters', 'characters.id', '=', 'character_sprites.character_id')
            ->where('character_sprites.has_image', 1)
            ->where('characters.user_id', $user->id)
            ->where('characters.is_myo_slot', 0)
            ->with(['character:id,slug,name,is_myo_slot'])
            ->orderBy('character_sprites.sort', 'DESC')
            ->get()
            ->map(function ($sprite) use ($placedCounts) {
                $placed = (int) ($placedCounts[$sprite->id] ?? 0);

                return (object) [
                    'sprite' => $sprite,
                    'quantity' => 1,
                    'placed' => $placed,
                    'available' => max(0, 1 - $placed),
                ];
            })
            ->filter(function ($entry) {
                return $entry->sprite->imageUrl;
            })
            ->values();
    }

    /**
     * Build sprite catalog data for the editor client.
     *
     * @param  \Illuminate\Support\Collection  $spriteInventory
     * @return array
     */
    public function buildSpriteCatalog($spriteInventory)
    {
        $catalog = [];
        $size = HomesteadConfig::defaultSpriteSize();

        foreach ($spriteInventory as $entry) {
            $sprite = $entry->sprite;
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

    /**
     * Get placed sprite counts keyed by sprite ID for a room.
     *
     * @param  \App\Models\Homestead\RoomSave  $room
     * @return array<int, int>
     */
    protected function getPlacedSpriteCountsForRoom($room)
    {
        if ($room->relationLoaded('placements')) {
            return $room->placements
                ->whereNotNull('character_sprite_id')
                ->countBy('character_sprite_id')
                ->all();
        }

        return RoomPlacement::query()
            ->where('room_save_id', $room->id)
            ->whereNotNull('character_sprite_id')
            ->selectRaw('character_sprite_id, COUNT(*) as placed_count')
            ->groupBy('character_sprite_id')
            ->pluck('placed_count', 'character_sprite_id')
            ->all();
    }

    /**
     * Validate and normalize sprite placements for persistence.
     *
     * @param  array                             $placements
     * @param  \App\Models\User\User             $user
     * @param  \App\Models\Homestead\RoomSave    $room
     * @param  array                             $settings
     * @param  array                             $canvas
     * @return array
     */
    protected function normalizeSpritePlacements($placements, $user, $room, $settings, $canvas)
    {
        $canvasWidth = $canvas['width'];
        $canvasHeight = $canvas['height'];
        $size = HomesteadConfig::defaultSpriteSize();
        $defaultWidth = $size['width'];
        $defaultHeight = $size['height'];
        $spriteCounts = [];
        $normalized = [];

        $spriteIds = collect($placements)->pluck('character_sprite_id')->filter()->unique()->all();
        $ownedSpriteIds = [];

        if ($spriteIds) {
            $ownedSpriteIds = CharacterSprite::query()
                ->select('character_sprites.id')
                ->join('characters', 'characters.id', '=', 'character_sprites.character_id')
                ->where('character_sprites.has_image', 1)
                ->whereIn('character_sprites.id', $spriteIds)
                ->where('characters.user_id', $user->id)
                ->where('characters.is_myo_slot', 0)
                ->pluck('character_sprites.id')
                ->flip()
                ->all();
        }

        foreach ($placements as $placement) {
            if (!is_array($placement) || empty($placement['character_sprite_id'])) {
                continue;
            }

            if (!isset($placement['x'], $placement['y'], $placement['z_index'])) {
                throw new \Exception('Invalid placement data.');
            }

            $spriteId = (int) $placement['character_sprite_id'];
            if (!isset($ownedSpriteIds[$spriteId])) {
                throw new \Exception('One or more placed sprites are unavailable.');
            }

            $width = isset($placement['width']) ? (float) $placement['width'] : $defaultWidth;
            $height = isset($placement['height']) ? (float) $placement['height'] : $defaultHeight;
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

            $spriteCounts[$spriteId] = ($spriteCounts[$spriteId] ?? 0) + 1;
            $normalized[] = [
                'character_sprite_id' => $spriteId,
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
                'z_index' => $zIndex,
            ];
        }

        foreach ($spriteCounts as $spriteId => $count) {
            if ($count > 1) {
                throw new \Exception('Each character sprite can only be placed once in a room.');
            }
        }

        return $normalized;
    }

    /**
     * Build sprite catalog including sprites already placed in the room.
     *
     * @param  \Illuminate\Support\Collection  $spriteInventory
     * @param  \Illuminate\Support\Collection  $placements
     * @return array
     */
    public function buildSpriteCatalogForRoom($spriteInventory, $placements)
    {
        $catalog = $this->buildSpriteCatalog($spriteInventory);

        $missingSpriteIds = $placements->pluck('character_sprite_id')->filter()->unique()->filter(function ($spriteId) use ($catalog) {
            return !isset($catalog[$spriteId]);
        });

        if ($missingSpriteIds->isEmpty()) {
            return $catalog;
        }

        $size = HomesteadConfig::defaultSpriteSize();

        foreach (CharacterSprite::whereIn('id', $missingSpriteIds)->with(['character:id,slug,name,is_myo_slot'])->get() as $sprite) {
            if (!$sprite->imageUrl) {
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
