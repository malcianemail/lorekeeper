<?php

namespace App\Services\Item;

use App\Services\Service;
use App\Services\Homestead\HomesteadConfig;
use App\Services\Item\Concerns\ActivatesSlotItem;
use App\Models\Character\Character;

class SpriteSlotService extends Service
{
    use ActivatesSlotItem;

    /**
     * Apply the slot activation effect for this item type.
     *
     * @param  \App\Models\User\UserItem  $stack
     * @param  \App\Models\User\User      $user
     * @param  array                      $data
     * @return void
     */
    protected function applySlotActivation($stack, $user, $data)
    {
        $characterId = isset($data['character_id']) ? (int) $data['character_id'] : 0;
        if (!$characterId) {
            throw new \Exception('Please select a character to receive the sprite slot.');
        }

        $character = Character::myo(0)
            ->where('user_id', $user->id)
            ->where('id', $characterId)
            ->first();

        if (!$character) {
            throw new \Exception('Invalid character selected.');
        }

        $character->increment('max_sprite_slots');
    }

    /**
     * Get the item tag handled by this service.
     *
     * @return string
     */
    protected function slotTag()
    {
        return HomesteadConfig::spriteSlotTag();
    }
}
