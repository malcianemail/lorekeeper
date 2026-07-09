<?php

namespace App\Services\Character\Concerns;

use App\Models\Character\Character;
use App\Services\Homestead\HomesteadConfig;

trait ManagesCharacterSpriteSlots
{
    /**
     * Get sprite slot usage summary for a character.
     *
     * @param  \App\Models\Character\Character  $character
     * @return array
     */
    public function getSpriteSlotSummary(Character $character)
    {
        $used = $character->sprites()->count();
        $max = max((int) HomesteadConfig::baseSpriteSlots(), (int) $character->max_sprite_slots);

        return [
            'max' => $max,
            'used' => $used,
            'remaining' => max(0, $max - $used),
            'can_create' => $used < $max,
        ];
    }
}
