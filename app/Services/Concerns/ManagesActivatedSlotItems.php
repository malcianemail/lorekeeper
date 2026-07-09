<?php

namespace App\Services\Concerns;

use App\Models\User\UserItem;

trait ManagesActivatedSlotItems
{
    /**
     * Get the number of activated inventory slot items for a tag.
     *
     * @param  \App\Models\User\User  $user
     * @param  string                 $tag
     * @return int
     */
    public function getActivatedSlotCount($user, $tag)
    {
        return UserItem::where('user_id', $user->id)
            ->where('activated_quantity', '>', 0)
            ->whereHas('item.tags', function ($query) use ($tag) {
                $query->where('tag', $tag)->where('is_active', 1);
            })
            ->sum('activated_quantity');
    }

    /**
     * Get how many slot items from a stack can still be activated.
     *
     * @param  \App\Models\User\UserItem  $stack
     * @return int
     */
    public function getActivatableSlotQuantity(UserItem $stack)
    {
        $inactive = max(0, (int) $stack->count - (int) ($stack->activated_quantity ?? 0));

        return min($stack->availableQuantity, $inactive);
    }
}
