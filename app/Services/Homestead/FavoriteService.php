<?php

namespace App\Services\Homestead;

use DB;
use App\Services\Service;
use App\Models\Homestead\HomesteadFavorite;
use App\Services\Homestead\Concerns\ResolvesHomesteadRefTargets;

class FavoriteService extends Service
{
    use ResolvesHomesteadRefTargets;

    /*
    |--------------------------------------------------------------------------
    | Favorite Service
    |--------------------------------------------------------------------------
    |
    | User favorites for homestead rooms, houses, and characters.
    |
    */

    /**
     * Toggle favorite status for the authenticated user.
     *
     * @param  \App\Models\User\User  $user
     * @param  string                 $refType
     * @param  int                    $refId
     * @return array{favorited: bool, count: int}|false
     */
    public function toggleFavorite($user, $refType, $refId)
    {
        DB::beginTransaction();

        try {
            $subject = $this->resolveRefSubject($refType, (int) $refId);
            if (!$subject) {
                throw new \Exception('This content is unavailable.');
            }

            if ($this->userOwnsSubject($user, $subject)) {
                throw new \Exception('You cannot favorite your own content.');
            }

            $existing = HomesteadFavorite::query()
                ->where('user_id', $user->id)
                ->forTarget($refType, $refId)
                ->first();

            if ($existing) {
                $existing->delete();
                $favorited = false;
            } else {
                HomesteadFavorite::create([
                    'user_id' => $user->id,
                    'ref_type' => $refType,
                    'ref_id' => (int) $refId,
                ]);
                $favorited = true;
            }

            $result = [
                'favorited' => $favorited,
                'count' => $this->getFavoriteCount($refType, $refId),
            ];

            return $this->commitReturn($result);
        } catch (\Exception $e) {
            $this->setError('error', $e->getMessage());
        }

        return $this->rollbackReturn(false);
    }

    /**
     * Whether a user has favorited a target.
     *
     * @param  \App\Models\User\User|null  $user
     * @param  string                      $refType
     * @param  int                         $refId
     * @return bool
     */
    public function isFavorited($user, $refType, $refId)
    {
        if (!$user) {
            return false;
        }

        return HomesteadFavorite::query()
            ->where('user_id', $user->id)
            ->forTarget($refType, $refId)
            ->exists();
    }

    /**
     * Get favorite count for a target.
     *
     * @param  string  $refType
     * @param  int     $refId
     * @return int
     */
    public function getFavoriteCount($refType, $refId)
    {
        return HomesteadFavorite::query()
            ->forTarget($refType, $refId)
            ->count();
    }

    /**
     * Get favorite counts keyed by ref_id for a type.
     *
     * @param  string  $refType
     * @param  array   $refIds
     * @return array<int, int>
     */
    public function getFavoriteCounts($refType, array $refIds)
    {
        if (!$refIds) {
            return [];
        }

        return HomesteadFavorite::query()
            ->where('ref_type', $refType)
            ->whereIn('ref_id', $refIds)
            ->selectRaw('ref_id, COUNT(*) as favorite_count')
            ->groupBy('ref_id')
            ->pluck('favorite_count', 'ref_id')
            ->all();
    }

    /**
     * Build button state for a favorite target.
     *
     * @param  \App\Models\User\User|null  $user
     * @param  string                      $refType
     * @param  int                         $refId
     * @return array
     */
    public function getFavoriteButtonState($user, $refType, $refId)
    {
        $canFavorite = false;

        if ($user) {
            $subject = $this->resolveRefSubject($refType, (int) $refId);
            $canFavorite = $subject && !$this->userOwnsSubject($user, $subject);
        }

        return [
            'refType' => $refType,
            'refId' => (int) $refId,
            'isFavorited' => $this->isFavorited($user, $refType, $refId),
            'count' => $this->getFavoriteCount($refType, $refId),
            'canFavorite' => $canFavorite,
        ];
    }

    /**
     * Get paginated favorites for a user.
     *
     * @param  \App\Models\User\User  $user
     * @param  string|null            $type
     * @param  int                    $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserFavorites($user, $type = null, $perPage = 12)
    {
        $favorites = HomesteadFavorite::query()
            ->where('user_id', $user->id)
            ->ofType($type)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->hydrateRefSubjects($favorites);
    }

    /**
     * Get recent favorites for profile preview.
     *
     * @param  \App\Models\User\User  $user
     * @param  int                    $limit
     * @return \Illuminate\Support\Collection
     */
    public function getProfileFavoritesPreview($user, $limit = 4)
    {
        $favorites = HomesteadFavorite::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $this->hydrateRefSubjects($favorites);
    }

    /**
     * Whether the user owns the favoritable subject.
     *
     * @param  \App\Models\User\User  $user
     * @param  mixed                  $subject
     * @return bool
     */
    protected function userOwnsSubject($user, $subject)
    {
        return isset($subject->user_id) && (int) $subject->user_id === (int) $user->id;
    }
}
