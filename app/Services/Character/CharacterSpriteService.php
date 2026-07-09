<?php

namespace App\Services\Character;

use DB;
use App\Services\Service;
use App\Models\User\User;
use App\Models\Character\Character;
use App\Models\Character\CharacterSprite;
use App\Models\Homestead\RoomLayout;
use App\Models\Homestead\RoomPlacement;
use App\Services\Character\Concerns\ManagesCharacterSpriteSlots;
use App\Services\Homestead\HomesteadConfig;

class CharacterSpriteService extends Service
{
    use ManagesCharacterSpriteSlots;
    /**
     * Validation rules for sprite image uploads.
     *
     * @var array
     */
    public static $imageRules = [
        'image' => 'required|mimes:jpeg,jpg,gif,png|max:20000',
    ];

    /**
     * Validation rules for sprite updates.
     *
     * @var array
     */
    public static $updateRules = [
        'name' => 'nullable|string|max:100',
        'image' => 'nullable|mimes:jpeg,jpg,gif,png|max:20000',
    ];

    /**
     * Determine whether the user can manage sprites for a character.
     *
     * @param  \App\Models\User\User         $user
     * @param  \App\Models\Character\Character  $character
     * @return bool
     */
    public function canManage($user, Character $character)
    {
        return $user && ($character->user_id == $user->id || $user->hasPower('manage_characters'));
    }

    /**
     * Create a sprite for a character.
     *
     * @param  array                         $data
     * @param  \App\Models\Character\Character  $character
     * @param  \App\Models\User\User         $user
     * @return \App\Models\Character\CharacterSprite|bool
     */
    public function createSprite($data, Character $character, User $user)
    {
        DB::beginTransaction();

        try {
            if (!$this->canManage($user, $character)) {
                throw new \Exception('You are not authorized to manage sprites for this character.');
            }

            if (!$this->getSpriteSlotSummary($character)['can_create'] && !$user->hasPower('manage_characters')) {
                throw new \Exception('This character has reached its sprite slot limit.');
            }

            if (!isset($data['image']) || !$data['image']) {
                throw new \Exception('Please select an image to upload.');
            }

            $sprite = CharacterSprite::create([
                'character_id' => $character->id,
                'name' => isset($data['name']) && trim($data['name']) ? trim($data['name']) : null,
                'sort' => $this->getNextSortValue($character),
                'has_image' => 0,
                'extension' => strtolower($data['image']->getClientOriginalExtension()),
            ]);

            if (!$this->handleImage($data['image'], $sprite->imagePath, $sprite->imageFileName)) {
                throw new \Exception('Failed to upload sprite image.');
            }

            $sprite->has_image = 1;
            $sprite->save();

            if (!$character->active_sprite_id) {
                $character->update(['active_sprite_id' => $sprite->id]);
            }

            return $this->commitReturn($sprite);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Update a sprite's name and/or image.
     *
     * @param  array                              $data
     * @param  \App\Models\Character\CharacterSprite  $sprite
     * @param  \App\Models\User\User              $user
     * @return \App\Models\Character\CharacterSprite|bool
     */
    public function updateSprite($data, CharacterSprite $sprite, User $user)
    {
        DB::beginTransaction();

        try {
            $character = $sprite->character;
            if (!$character || !$this->canManage($user, $character)) {
                throw new \Exception('You are not authorized to manage sprites for this character.');
            }

            $sprite->name = isset($data['name']) && trim($data['name']) ? trim($data['name']) : null;

            if (isset($data['image']) && $data['image']) {
                $oldFileName = $sprite->has_image ? $sprite->imageFileName : null;
                $sprite->extension = strtolower($data['image']->getClientOriginalExtension());
                $sprite->has_image = 1;

                if (!$this->handleImage($data['image'], $sprite->imagePath, $sprite->imageFileName, $oldFileName)) {
                    throw new \Exception('Failed to upload sprite image.');
                }
            }

            $sprite->save();

            return $this->commitReturn($sprite);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Delete a sprite.
     *
     * @param  \App\Models\Character\CharacterSprite  $sprite
     * @param  \App\Models\User\User              $user
     * @return bool
     */
    public function deleteSprite(CharacterSprite $sprite, User $user)
    {
        DB::beginTransaction();

        try {
            $character = $sprite->character;
            if (!$character || !$this->canManage($user, $character)) {
                throw new \Exception('You are not authorized to manage sprites for this character.');
            }

            RoomLayout::where('character_sprite_id', $sprite->id)->update(['character_sprite_id' => null]);
            RoomPlacement::where('character_sprite_id', $sprite->id)->update(['character_sprite_id' => null]);

            if ($sprite->has_image && $sprite->extension && file_exists($sprite->imagePath . '/' . $sprite->imageFileName)) {
                $this->deleteImage($sprite->imagePath, $sprite->imageFileName);
            }

            $wasActive = $character->active_sprite_id == $sprite->id;
            if ($wasActive) {
                $replacement = $character->sprites()->where('id', '!=', $sprite->id)->orderBy('sort', 'DESC')->first();
                $character->update(['active_sprite_id' => $replacement ? $replacement->id : null]);
            }

            $sprite->delete();

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Set the active/default sprite for a character.
     *
     * @param  \App\Models\Character\CharacterSprite  $sprite
     * @param  \App\Models\User\User              $user
     * @return bool
     */
    public function setActiveSprite(CharacterSprite $sprite, User $user)
    {
        DB::beginTransaction();

        try {
            $character = $sprite->character;
            if (!$character || !$this->canManage($user, $character)) {
                throw new \Exception('You are not authorized to manage sprites for this character.');
            }

            if (!$sprite->has_image) {
                throw new \Exception('This sprite does not have an image.');
            }

            $character->update(['active_sprite_id' => $sprite->id]);

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Reorder sprites for a character.
     *
     * @param  array                         $spriteIds
     * @param  \App\Models\Character\Character  $character
     * @param  \App\Models\User\User         $user
     * @return bool
     */
    public function sortSprites($spriteIds, Character $character, User $user)
    {
        DB::beginTransaction();

        try {
            if (!$this->canManage($user, $character)) {
                throw new \Exception('You are not authorized to manage sprites for this character.');
            }

            if (!is_array($spriteIds)) {
                throw new \Exception('Invalid sort data.');
            }

            $ownedIds = $character->sprites()->pluck('id')->all();
            $spriteIds = array_values(array_filter(array_map('intval', $spriteIds)));

            if (count($spriteIds) !== count($ownedIds) || array_diff($spriteIds, $ownedIds)) {
                throw new \Exception('Invalid sprite sort order.');
            }

            $sort = count($spriteIds);
            foreach ($spriteIds as $spriteId) {
                CharacterSprite::where('id', $spriteId)
                    ->where('character_id', $character->id)
                    ->update(['sort' => $sort]);
                $sort--;
            }

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Update the maximum sprite slots for a character (admin).
     *
     * @param  \App\Models\Character\Character  $character
     * @param  int                                $maxSlots
     * @param  \App\Models\User\User            $user
     * @return bool
     */
    public function updateMaxSpriteSlots(Character $character, $maxSlots, User $user)
    {
        DB::beginTransaction();

        try {
            if (!$user->hasPower('manage_characters')) {
                throw new \Exception('You are not authorized to manage sprite slots.');
            }

            $maxSlots = max((int) HomesteadConfig::baseSpriteSlots(), (int) $maxSlots);
            $used = $character->sprites()->count();

            if ($maxSlots < $used) {
                throw new \Exception('Slot limit cannot be lower than the number of uploaded sprites (' . $used . ').');
            }

            $character->update(['max_sprite_slots' => $maxSlots]);

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Get the next sort value for a new sprite.
     *
     * @param  \App\Models\Character\Character  $character
     * @return int
     */
    protected function getNextSortValue(Character $character)
    {
        $max = $character->sprites()->max('sort');

        return is_null($max) ? 0 : ((int) $max + 1);
    }
}
