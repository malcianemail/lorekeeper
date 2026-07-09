<?php

namespace App\Services\Homestead\Concerns;

use App\Models\Homestead\HomesteadFavorite;
use App\Models\Homestead\FeaturedItem;
use App\Models\Homestead\RoomSave;
use App\Models\Character\Character;

trait ResolvesHomesteadRefTargets
{
    /**
     * Resolve a homestead favorite/featured subject by type and ID.
     *
     * @param  string  $type
     * @param  int     $refId
     * @return \App\Models\Homestead\RoomSave|\App\Models\Character\Character|null
     */
    protected function resolveRefSubject($type, $refId)
    {
        switch ($type) {
            case HomesteadFavorite::TYPE_ROOM:
                return RoomSave::indoor()->find($refId);
            case HomesteadFavorite::TYPE_HOUSE:
                return RoomSave::outdoor()->find($refId);
            case HomesteadFavorite::TYPE_CHARACTER:
                return Character::myo(0)->visible()->find($refId);
        }

        return null;
    }

    /**
     * Attach resolved subject models to polymorphic homestead rows.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $items
     * @param  string  $typeKey
     * @param  string  $idKey
     * @return mixed
     */
    protected function hydrateRefSubjects($items, $typeKey = 'ref_type', $idKey = 'ref_id')
    {
        $collection = $items instanceof \Illuminate\Pagination\LengthAwarePaginator
            ? $items->getCollection()
            : $items;

        $roomIds = $collection->where($typeKey, HomesteadFavorite::TYPE_ROOM)->pluck($idKey)->unique()->filter()->all();
        $houseIds = $collection->where($typeKey, HomesteadFavorite::TYPE_HOUSE)->pluck($idKey)->unique()->filter()->all();
        $characterIds = $collection->where($typeKey, HomesteadFavorite::TYPE_CHARACTER)->pluck($idKey)->unique()->filter()->all();

        $spaceIds = collect($roomIds)->merge($houseIds)->unique()->filter()->all();
        $spaces = $spaceIds
            ? RoomSave::with('user')->whereIn('id', $spaceIds)->get()->keyBy('id')
            : collect();
        $characters = $characterIds
            ? Character::with(['user', 'image'])->myo(0)->visible()->whereIn('id', $characterIds)->get()->keyBy('id')
            : collect();

        $collection->transform(function ($row) use ($typeKey, $idKey, $spaces, $characters) {
            switch ($row->{$typeKey}) {
                case HomesteadFavorite::TYPE_ROOM:
                case HomesteadFavorite::TYPE_HOUSE:
                    $row->setRelation('subject', $spaces->get($row->{$idKey}));
                    break;
                case HomesteadFavorite::TYPE_CHARACTER:
                    $row->setRelation('subject', $characters->get($row->{$idKey}));
                    break;
            }

            return $row;
        });

        $this->attachShowcaseUrls($collection, $typeKey, $idKey);

        if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $items->setCollection($collection);
            return $items;
        }

        return $collection;
    }

    /**
     * Attach batched favorite button state arrays to homestead rows.
     *
     * @param  \App\Models\User\User|null  $user
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator  $items
     * @param  string                      $typeKey
     * @param  string                      $idKey
     * @return mixed
     */
    public function attachFavoriteButtonStates($user, $items, $typeKey = 'ref_type', $idKey = 'ref_id')
    {
        $collection = $items instanceof \Illuminate\Pagination\LengthAwarePaginator
            ? $items->getCollection()
            : $items;

        $states = $this->getFavoriteButtonStatesForTargets(
            $user,
            $collection->map(function ($row) use ($typeKey, $idKey) {
                return [
                    'type' => $row->{$typeKey},
                    'id' => (int) $row->{$idKey},
                ];
            })->all()
        );

        $collection->transform(function ($row) use ($states, $typeKey, $idKey) {
            $key = $row->{$typeKey} . ':' . $row->{$idKey};
            $row->favorite_state = $states[$key] ?? $this->getFavoriteButtonState($user, $row->{$typeKey}, $row->{$idKey});

            return $row;
        });

        if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $items->setCollection($collection);
            return $items;
        }

        return $collection;
    }

    /**
     * Build favorite button states for multiple targets in batched queries.
     *
     * @param  \App\Models\User\User|null  $user
     * @param  array                       $targets
     * @return array<string, array>
     */
    public function getFavoriteButtonStatesForTargets($user, array $targets)
    {
        $targets = collect($targets)
            ->filter(function ($target) {
                return !empty($target['type']) && !empty($target['id']);
            })
            ->unique(function ($target) {
                return $target['type'] . ':' . $target['id'];
            })
            ->values();

        if ($targets->isEmpty()) {
            return [];
        }

        $countsByType = [];
        foreach ($targets->groupBy('type') as $type => $typeTargets) {
            $countsByType[$type] = $this->getFavoriteCounts(
                $type,
                $typeTargets->pluck('id')->map(function ($id) {
                    return (int) $id;
                })->all()
            );
        }

        $favoritedKeys = [];
        if ($user) {
            $favorites = HomesteadFavorite::query()
                ->where('user_id', $user->id)
                ->where(function ($query) use ($targets) {
                    foreach ($targets->groupBy('type') as $type => $typeTargets) {
                        $query->orWhere(function ($typeQuery) use ($type, $typeTargets) {
                            $typeQuery->where('ref_type', $type)
                                ->whereIn('ref_id', $typeTargets->pluck('id')->map(function ($id) {
                                    return (int) $id;
                                })->all());
                        });
                    }
                })
                ->get(['ref_type', 'ref_id']);

            foreach ($favorites as $favorite) {
                $favoritedKeys[$favorite->ref_type . ':' . $favorite->ref_id] = true;
            }
        }

        $states = [];
        foreach ($targets as $target) {
            $key = $target['type'] . ':' . $target['id'];
            $subject = $this->resolveRefSubject($target['type'], (int) $target['id']);

            $states[$key] = [
                'refType' => $target['type'],
                'refId' => (int) $target['id'],
                'isFavorited' => isset($favoritedKeys[$key]),
                'count' => (int) ($countsByType[$target['type']][$target['id']] ?? 0),
                'canFavorite' => $user && $subject && !$this->userOwnsSubject($user, $subject),
            ];
        }

        return $states;
    }

    /**
     * Attach showcase detail URLs for room/house favorites in one query.
     *
     * @param  \Illuminate\Support\Collection  $collection
     * @param  string                          $typeKey
     * @param  string                          $idKey
     * @return void
     */
    protected function attachShowcaseUrls($collection, $typeKey = 'ref_type', $idKey = 'ref_id')
    {
        $spaceRows = $collection->filter(function ($row) use ($typeKey) {
            return in_array($row->{$typeKey}, [HomesteadFavorite::TYPE_ROOM, HomesteadFavorite::TYPE_HOUSE], true);
        });

        if ($spaceRows->isEmpty()) {
            return;
        }

        $featuredEntries = FeaturedItem::query()
            ->where(function ($query) use ($spaceRows, $typeKey, $idKey) {
                foreach ($spaceRows->groupBy($typeKey) as $type => $rows) {
                    $query->orWhere(function ($typeQuery) use ($type, $rows, $idKey) {
                        $typeQuery->where('type', $type)
                            ->whereIn('ref_id', $rows->pluck($idKey)->map(function ($id) {
                                return (int) $id;
                            })->all());
                    });
                }
            })
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get(['id', 'type', 'ref_id', 'is_active']);

        $urlByTarget = [];
        foreach ($featuredEntries as $featured) {
            $key = $featured->type . ':' . $featured->ref_id;
            if (!isset($urlByTarget[$key])) {
                $urlByTarget[$key] = $featured->url;
            }
        }

        $collection->transform(function ($row) use ($typeKey, $idKey, $urlByTarget) {
            if (!in_array($row->{$typeKey}, [HomesteadFavorite::TYPE_ROOM, HomesteadFavorite::TYPE_HOUSE], true)) {
                return $row;
            }

            $row->showcase_url = $urlByTarget[$row->{$typeKey} . ':' . $row->{$idKey}] ?? null;

            return $row;
        });
    }
}
