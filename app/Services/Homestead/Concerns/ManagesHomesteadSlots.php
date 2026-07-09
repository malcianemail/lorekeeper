<?php

namespace App\Services\Homestead\Concerns;

use App\Services\Concerns\ManagesActivatedSlotItems;
use App\Services\Homestead\HomesteadConfig;
use App\Models\Homestead\RoomSave;

trait ManagesHomesteadSlots
{
    use ManagesActivatedSlotItems;

    /**
     * Get slot usage summary for a user and room type.
     *
     * @param  \App\Models\User\User  $user
     * @param  string                 $roomType
     * @return array
     */
    public function getSlotSummary($user, $roomType)
    {
        $used = $this->getUsedSlots($user, $roomType);

        if ($this->bypassesSlotLimits($user)) {
            return [
                'max' => null,
                'used' => $used,
                'remaining' => null,
                'can_create' => true,
                'unlimited' => true,
            ];
        }

        $max = $this->getMaxSlots($user, $roomType);

        return [
            'max' => $max,
            'used' => $used,
            'remaining' => max(0, $max - $used),
            'can_create' => $used < $max,
            'unlimited' => false,
        ];
    }

    /**
     * Get the maximum number of slots available for a room type.
     *
     * @param  \App\Models\User\User  $user
     * @param  string                 $roomType
     * @return int
     */
    public function getMaxSlots($user, $roomType)
    {
        return HomesteadConfig::baseSlots($roomType)
            + $this->getActivatedSlotCount($user, HomesteadConfig::slotTag($roomType));
    }

    /**
     * Get the number of rooms or houses a user has created.
     *
     * @param  \App\Models\User\User  $user
     * @param  string                 $roomType
     * @return int
     */
    public function getUsedSlots($user, $roomType)
    {
        return RoomSave::where('user_id', $user->id)
            ->where('room_type', $roomType)
            ->count();
    }

    /**
     * Whether the user bypasses homestead slot limits.
     *
     * @param  \App\Models\User\User  $user
     * @return bool
     */
    protected function bypassesSlotLimits($user)
    {
        $power = HomesteadConfig::unlimitedSlotsPower();

        return $power && $user->hasPower($power);
    }
}
