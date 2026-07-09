<?php

namespace App\Services\Homestead\Concerns;

use DB;
use App\Services\Homestead\HomesteadConfig;
use App\Models\Homestead\RoomSave;
use App\Models\Homestead\RoomLayout;
use App\Models\Homestead\RoomPlacement;
use App\Models\Homestead\HomesteadFavorite;
use App\Services\Homestead\Concerns\CleansHomesteadReferences;

trait ManagesHomesteadSpaces
{
    use CleansHomesteadReferences;
    /**
     * Create a room or house for a user.
     *
     * @param  array                  $data
     * @param  \App\Models\User\User  $user
     * @param  string                 $roomType
     * @return \App\Models\Homestead\RoomSave|bool
     */
    public function createRoom($data, $user, $roomType = RoomSave::TYPE_INDOOR)
    {
        DB::beginTransaction();

        try {
            if (!isset($data['name']) || !trim($data['name'])) {
                throw new \Exception('Please enter a name.');
            }

            if (!$this->getSlotSummary($user, $roomType)['can_create']) {
                $label = HomesteadConfig::spaceLabels($roomType)['singular'] ?? 'space';
                throw new \Exception('You have reached your ' . $label . ' slot limit.');
            }

            $room = RoomSave::create([
                'user_id' => $user->id,
                'name' => trim($data['name']),
                'room_type' => $roomType,
            ]);

            RoomLayout::create([
                'room_save_id' => $room->id,
            ]);

            return $this->commitReturn($room);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Update a room or house.
     *
     * @param  array                  $data
     * @param  \App\Models\User\User  $user
     * @return \App\Models\Homestead\RoomSave|bool
     */
    public function updateRoom($data, $user)
    {
        DB::beginTransaction();

        try {
            if (!isset($data['room_id'])) {
                throw new \Exception('Invalid room selected.');
            }

            $room = $this->getUserRoom($data['room_id'], $user);
            if (!$room) {
                throw new \Exception('Invalid room selected.');
            }

            if (!isset($data['name']) || !trim($data['name'])) {
                throw new \Exception('Please enter a name.');
            }

            $room->update([
                'name' => trim($data['name']),
            ]);

            return $this->commitReturn($room);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Delete a room or house.
     *
     * @param  array                  $data
     * @param  \App\Models\User\User  $user
     * @return bool
     */
    public function deleteRoom($data, $user)
    {
        DB::beginTransaction();

        try {
            if (!isset($data['room_id'])) {
                throw new \Exception('Invalid room selected.');
            }

            $room = $this->getUserRoom($data['room_id'], $user);
            if (!$room) {
                throw new \Exception('Invalid room selected.');
            }

            RoomPlacement::where('room_save_id', $room->id)->delete();
            RoomLayout::where('room_save_id', $room->id)->delete();

            $refType = $room->room_type === RoomSave::TYPE_OUTDOOR
                ? HomesteadFavorite::TYPE_HOUSE
                : HomesteadFavorite::TYPE_ROOM;
            $this->cleanupHomesteadReferences($refType, $room->id);

            $room->delete();

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Get a room owned by the user.
     *
     * @param  int                    $id
     * @param  \App\Models\User\User  $user
     * @return \App\Models\Homestead\RoomSave|null
     */
    public function getUserRoom($id, $user, $roomType = null)
    {
        $query = RoomSave::where('id', $id)
            ->where('user_id', $user->id);

        if ($roomType) {
            $query->where('room_type', $roomType);
        }

        return $query->first();
    }

    /**
     * Ensure the space belongs to the user.
     *
     * @param  \App\Models\User\User             $user
     * @param  \App\Models\Homestead\RoomSave    $room
     * @return void
     */
    protected function assertUserOwnsSpace($user, $room)
    {
        if ((int) $room->user_id !== (int) $user->id) {
            throw new \Exception('Invalid room selected.');
        }
    }
}
