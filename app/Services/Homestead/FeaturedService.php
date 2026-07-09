<?php

namespace App\Services\Homestead;

use DB;
use App\Services\Service;
use App\Models\Homestead\FeaturedItem;
use App\Models\Homestead\RoomSave;
use App\Models\Character\Character;

class FeaturedService extends Service
{
    /*
    |--------------------------------------------------------------------------
    | Featured Service
    |--------------------------------------------------------------------------
    |
    | Moderator curation and public showcase for featured content.
    |
    */

    /**
     * Get paginated featured entries for the public showcase.
     *
     * @param  string|null  $type
     * @param  int          $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPublicFeatured($type = null, $perPage = 12)
    {
        $items = FeaturedItem::query()
            ->active()
            ->ofType($type)
            ->ordered()
            ->paginate($perPage);

        return $this->hydrateFeaturedSubjects($items);
    }

    /**
     * Get all featured entries for admin management.
     *
     * @param  string|null  $type
     * @return \Illuminate\Support\Collection
     */
    public function getAdminFeatured($type = null)
    {
        $items = FeaturedItem::query()
            ->with('ownerUser')
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->ordered()
            ->get();

        return $this->hydrateFeaturedSubjects($items);
    }

    /**
     * Get a featured entry for the public detail page.
     *
     * @param  int  $id
     * @return \App\Models\Homestead\FeaturedItem|null
     */
    public function getPublicFeaturedEntry($id)
    {
        $featured = FeaturedItem::query()
            ->active()
            ->with('ownerUser')
            ->find($id);

        if (!$featured) {
            return null;
        }

        return $this->hydrateFeaturedSubjects(collect([$featured]))->first();
    }

    /**
     * Get a featured entry for admin editing.
     *
     * @param  int  $id
     * @return \App\Models\Homestead\FeaturedItem|null
     */
    public function getAdminFeaturedEntry($id)
    {
        $featured = FeaturedItem::query()
            ->with('ownerUser')
            ->find($id);

        if (!$featured) {
            return null;
        }

        return $this->hydrateFeaturedSubjects(collect([$featured]))->first();
    }

    /**
     * Create a featured entry.
     *
     * @param  array  $data
     * @return bool
     */
    public function createFeatured($data)
    {
        DB::beginTransaction();

        try {
            $subject = $this->resolveSubject($data['type'], (int) $data['ref_id']);
            if (!$subject) {
                throw new \Exception('The selected content could not be found.');
            }

            $existing = FeaturedItem::where('type', $data['type'])->where('ref_id', $data['ref_id'])->first();
            if ($existing) {
                if ($existing->is_active) {
                    throw new \Exception('This content is already featured.');
                }

                $existing->update([
                    'owner_user_id' => $this->resolveOwnerUserId($subject),
                    'note' => $data['note'] ?? $existing->note,
                    'featured_order' => isset($data['featured_order']) ? (int) $data['featured_order'] : $existing->featured_order,
                    'is_active' => $this->normalizeBoolean($data['is_active'] ?? true),
                ]);

                return $this->commitReturn(true);
            }

            FeaturedItem::create([
                'type' => $data['type'],
                'ref_id' => (int) $data['ref_id'],
                'owner_user_id' => $this->resolveOwnerUserId($subject),
                'note' => $data['note'] ?? null,
                'featured_order' => isset($data['featured_order']) ? (int) $data['featured_order'] : $this->nextFeaturedOrder(),
                'is_active' => $this->normalizeBoolean($data['is_active'] ?? true),
            ]);

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Update a featured entry.
     *
     * @param  \App\Models\Homestead\FeaturedItem  $featured
     * @param  array                                 $data
     * @return bool
     */
    public function updateFeatured($featured, $data)
    {
        DB::beginTransaction();

        try {
            $featured->update([
                'note' => $data['note'] ?? null,
                'featured_order' => isset($data['featured_order']) ? (int) $data['featured_order'] : $featured->featured_order,
                'is_active' => array_key_exists('is_active', $data)
                    ? $this->normalizeBoolean($data['is_active'])
                    : $featured->is_active,
            ]);

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Toggle whether a featured entry is active.
     *
     * @param  \App\Models\Homestead\FeaturedItem  $featured
     * @return bool
     */
    public function toggleFeaturedActive($featured)
    {
        DB::beginTransaction();

        try {
            $featured->update([
                'is_active' => !$featured->is_active,
            ]);

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Delete a featured entry.
     *
     * @param  \App\Models\Homestead\FeaturedItem  $featured
     * @return bool
     */
    public function deleteFeatured($featured)
    {
        DB::beginTransaction();

        try {
            $featured->delete();

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Persist featured display order from an ordered ID list.
     *
     * @param  array  $orderedIds
     * @return bool
     */
    public function sortFeatured($orderedIds)
    {
        DB::beginTransaction();

        try {
            if (!is_array($orderedIds)) {
                throw new \Exception('Invalid sort data.');
            }

            foreach ($orderedIds as $index => $id) {
                FeaturedItem::where('id', (int) $id)->update([
                    'featured_order' => (int) $index,
                ]);
            }

            return $this->commitReturn(true);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Candidate rooms for the admin create form.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFeatureableRooms()
    {
        $featuredIds = $this->getFeaturedRefIds(FeaturedItem::TYPE_ROOM);

        return RoomSave::query()
            ->indoor()
            ->when($featuredIds, function ($query) use ($featuredIds) {
                $query->whereNotIn('id', $featuredIds);
            })
            ->with('user')
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    /**
     * Candidate houses for the admin create form.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFeatureableHouses()
    {
        $featuredIds = $this->getFeaturedRefIds(FeaturedItem::TYPE_HOUSE);

        return RoomSave::query()
            ->outdoor()
            ->when($featuredIds, function ($query) use ($featuredIds) {
                $query->whereNotIn('id', $featuredIds);
            })
            ->with('user')
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    /**
     * Candidate characters for the admin create form.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getFeatureableCharacters()
    {
        $featuredIds = $this->getFeaturedRefIds(FeaturedItem::TYPE_CHARACTER);

        return Character::query()
            ->myo(0)
            ->visible()
            ->when($featuredIds, function ($query) use ($featuredIds) {
                $query->whereNotIn('id', $featuredIds);
            })
            ->with(['user', 'image'])
            ->orderBy('number', 'DESC')
            ->limit(500)
            ->get();
    }

    /**
     * Get ref IDs already used for a featured type.
     *
     * @param  string  $type
     * @return array
     */
    protected function getFeaturedRefIds($type)
    {
        return FeaturedItem::query()
            ->active()
            ->where('type', $type)
            ->pluck('ref_id')
            ->all();
    }

    /**
     * Attach resolved subject models to featured entries.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $items
     * @return mixed
     */
    public function hydrateFeaturedSubjects($items)
    {
        $collection = $items instanceof \Illuminate\Pagination\LengthAwarePaginator
            ? $items->getCollection()
            : $items;

        $roomIds = $collection->where('type', FeaturedItem::TYPE_ROOM)->pluck('ref_id')->unique()->filter()->all();
        $houseIds = $collection->where('type', FeaturedItem::TYPE_HOUSE)->pluck('ref_id')->unique()->filter()->all();
        $characterIds = $collection->where('type', FeaturedItem::TYPE_CHARACTER)->pluck('ref_id')->unique()->filter()->all();

        $spaceIds = collect($roomIds)->merge($houseIds)->unique()->filter()->all();
        $spaces = $spaceIds
            ? RoomSave::with('user')->whereIn('id', $spaceIds)->get()->keyBy('id')
            : collect();
        $characters = $characterIds
            ? Character::with(['user', 'image'])->whereIn('id', $characterIds)->get()->keyBy('id')
            : collect();

        $collection->transform(function ($featured) use ($spaces, $characters) {
            switch ($featured->type) {
                case FeaturedItem::TYPE_ROOM:
                case FeaturedItem::TYPE_HOUSE:
                    $featured->setRelation('subject', $spaces->get($featured->ref_id));
                    break;
                case FeaturedItem::TYPE_CHARACTER:
                    $featured->setRelation('subject', $characters->get($featured->ref_id));
                    break;
            }

            return $featured;
        });

        if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $items->setCollection($collection);
            return $items;
        }

        return $collection;
    }

    /**
     * Resolve a featured subject by type and ID.
     *
     * @param  string  $type
     * @param  int     $refId
     * @return \App\Models\Homestead\RoomSave|\App\Models\Character\Character|null
     */
    protected function resolveSubject($type, $refId)
    {
        switch ($type) {
            case FeaturedItem::TYPE_ROOM:
                return RoomSave::indoor()->find($refId);
            case FeaturedItem::TYPE_HOUSE:
                return RoomSave::outdoor()->find($refId);
            case FeaturedItem::TYPE_CHARACTER:
                return Character::myo(0)->visible()->find($refId);
        }

        return null;
    }

    /**
     * Resolve the owner user ID from a featured subject.
     *
     * @param  mixed  $subject
     * @return int|null
     */
    protected function resolveOwnerUserId($subject)
    {
        return $subject && $subject->user_id ? (int) $subject->user_id : null;
    }

    /**
     * Get the next featured display order value.
     *
     * @return int
     */
    protected function nextFeaturedOrder()
    {
        $max = FeaturedItem::max('featured_order');

        return is_null($max) ? 0 : ((int) $max + 1);
    }

    /**
     * Normalize request boolean values.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function normalizeBoolean($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
