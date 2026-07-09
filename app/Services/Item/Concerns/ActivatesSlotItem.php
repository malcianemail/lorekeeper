<?php

namespace App\Services\Item\Concerns;

use DB;
use App\Services\Concerns\ManagesActivatedSlotItems;

trait ActivatesSlotItem
{
    use ManagesActivatedSlotItems;

    /**
     * Retrieves any data that should be used in the item tag editing form.
     *
     * @return array
     */
    public function getEditData()
    {
        return [];
    }

    /**
     * Processes the data attribute of the tag and returns it in the preferred format for edits.
     *
     * @param  \App\Models\Item\ItemTag  $tag
     * @return array
     */
    public function getTagData($tag)
    {
        return [];
    }

    /**
     * Processes the data attribute of the tag and returns it in the preferred format for DB storage.
     *
     * @param  \App\Models\Item\ItemTag  $tag
     * @param  array                     $data
     * @return bool
     */
    public function updateData($tag, $data)
    {
        return true;
    }

    /**
     * Acts upon the item when used from the inventory.
     *
     * @param  \Illuminate\Support\Collection|\App\Models\User\UserItem[]  $stacks
     * @param  \App\Models\User\User                                       $user
     * @param  array                                                       $data
     * @return bool
     */
    public function act($stacks, $user, $data)
    {
        DB::beginTransaction();

        try {
            $this->activateSlotStacks($stacks, $user, $data, function ($stack, $user, $data) {
                $this->applySlotActivation($stack, $user, $data);
            });

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Activate selected slot item stacks.
     *
     * @param  \Illuminate\Support\Collection|\App\Models\User\UserItem[]  $stacks
     * @param  \App\Models\User\User                                       $user
     * @param  array                                                       $data
     * @param  callable|null                                               $afterEach
     * @return void
     */
    protected function activateSlotStacks($stacks, $user, $data, callable $afterEach = null)
    {
        foreach ($stacks as $key => $stack) {
            if ($stack->user_id != $user->id) {
                throw new \Exception('This item does not belong to you.');
            }

            if (!$stack->item->hasTag($this->slotTag())) {
                throw new \Exception('Invalid slot item selected.');
            }

            $quantity = isset($data['quantities'][$key]) ? (int) $data['quantities'][$key] : 0;
            if ($quantity <= 0) {
                throw new \Exception('Please select a quantity to activate.');
            }

            if ($quantity > $this->getActivatableSlotQuantity($stack)) {
                throw new \Exception('You cannot activate more slot items than you have available.');
            }

            $stack->activated_quantity = (int) ($stack->activated_quantity ?? 0) + $quantity;
            $stack->save();

            if ($afterEach) {
                for ($i = 0; $i < $quantity; $i++) {
                    $afterEach($stack, $user, $data);
                }
            }
        }
    }

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
        // User-wide slot items do not require extra effects beyond activated_quantity.
    }

    /**
     * Get the item tag handled by this service.
     *
     * @return string
     */
    abstract protected function slotTag();
}
