<?php

namespace App\Http\Controllers\Homestead;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Homestead\FeaturedService;
use App\Services\Homestead\FavoriteService;
use App\Services\Homestead\RoomManager;
use App\Services\Homestead\HomesteadConfig;
use App\Models\Homestead\FeaturedItem;

class ShowcaseController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Showcase Controller
    |--------------------------------------------------------------------------
    |
    | Public gallery of moderator-curated featured content.
    |
    */

    /**
     * Shows the featured showcase listing.
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getIndex(Request $request, FeaturedService $service, RoomManager $roomManager, FavoriteService $favoriteService)
    {
        $type = $request->get('type');
        $validTypes = array_keys(HomesteadConfig::featuredTypeFilters());
        if ($type && !in_array($type, $validTypes, true)) {
            $type = null;
        }
        if ($type === 'all') {
            $type = null;
        }

        $featured = $service->getPublicFeatured($type);
        $spaceSubjects = $featured->getCollection()->filter(function ($entry) {
            return in_array($entry->type, [FeaturedItem::TYPE_ROOM, FeaturedItem::TYPE_HOUSE], true) && $entry->subject;
        })->pluck('subject');

        $previewDataByRoomId = $roomManager->getBatchPreviewViewData($spaceSubjects);
        $featured = $favoriteService->attachFavoriteButtonStates(
            Auth::user(),
            $featured,
            'type',
            'ref_id'
        );

        $featured->getCollection()->transform(function ($entry) use ($previewDataByRoomId) {
            if (in_array($entry->type, [FeaturedItem::TYPE_ROOM, FeaturedItem::TYPE_HOUSE], true) && $entry->subject) {
                $entry->preview_data = $previewDataByRoomId[$entry->subject->id] ?? null;
            }

            return $entry;
        });

        return view('homestead.showcase', [
            'featured' => $featured,
            'activeType' => $type,
            'typeFilters' => HomesteadConfig::featuredTypeFilters(),
            'labels' => [
                'title' => HomesteadConfig::featuredLabel('title'),
                'description' => HomesteadConfig::featuredLabel('description'),
                'empty_message' => HomesteadConfig::featuredLabel('empty_message'),
                'empty_hint' => HomesteadConfig::featuredLabel('empty_hint'),
            ],
        ]);
    }

    /**
     * Shows a featured entry detail page.
     *
     * @param  int                                     $id
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @param  \App\Services\Homestead\RoomManager      $roomManager
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getFeatured($id, FeaturedService $service, RoomManager $roomManager, FavoriteService $favoriteService)
    {
        $featured = $service->getPublicFeaturedEntry($id);
        if (!$featured || !$featured->subject) {
            abort(404);
        }

        $preview = null;
        if (in_array($featured->type, [FeaturedItem::TYPE_ROOM, FeaturedItem::TYPE_HOUSE], true)) {
            $preview = $roomManager->getPreviewViewData($featured->subject);
        }

        return view('homestead.showcase_detail', [
            'featured' => $featured,
            'preview' => $preview,
            'favoriteState' => $favoriteService->getFavoriteButtonState(Auth::user(), $featured->type, $featured->ref_id),
            'labels' => [
                'title' => HomesteadConfig::featuredLabel('title'),
            ],
        ]);
    }
}
