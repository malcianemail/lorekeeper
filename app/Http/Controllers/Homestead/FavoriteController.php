<?php

namespace App\Http\Controllers\Homestead;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Homestead\Concerns\FlashesServiceErrors;
use App\Services\Homestead\FavoriteService;
use App\Services\Homestead\RoomManager;
use App\Services\Homestead\HomesteadConfig;

class FavoriteController extends Controller
{
    use FlashesServiceErrors;

    /*
    |--------------------------------------------------------------------------
    | Favorite Controller
    |--------------------------------------------------------------------------
    |
    | User favorites for homestead rooms, houses, and characters.
    |
    */

    /**
     * Shows the authenticated user's favorites page.
     *
     * @param  \Illuminate\Http\Request                  $request
     * @param  \App\Services\Homestead\FavoriteService   $service
     * @param  \App\Services\Homestead\RoomManager       $roomManager
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getIndex(Request $request, FavoriteService $service, RoomManager $roomManager)
    {
        $type = $request->get('type');
        $validTypes = array_keys(HomesteadConfig::favoriteTypeFilters());
        if ($type && !in_array($type, $validTypes, true)) {
            $type = null;
        }
        if ($type === 'all') {
            $type = null;
        }

        $favorites = $service->getUserFavorites(Auth::user(), $type);
        $spaceSubjects = $favorites->getCollection()->filter(function ($favorite) {
            return in_array($favorite->ref_type, [
                \App\Models\Homestead\HomesteadFavorite::TYPE_ROOM,
                \App\Models\Homestead\HomesteadFavorite::TYPE_HOUSE,
            ], true) && $favorite->subject;
        })->pluck('subject');

        $previewDataByRoomId = $roomManager->getBatchPreviewViewData($spaceSubjects);
        $favorites->getCollection()->transform(function ($favorite) use ($previewDataByRoomId) {
            if (in_array($favorite->ref_type, [
                \App\Models\Homestead\HomesteadFavorite::TYPE_ROOM,
                \App\Models\Homestead\HomesteadFavorite::TYPE_HOUSE,
            ], true) && $favorite->subject) {
                $favorite->preview_data = $previewDataByRoomId[$favorite->subject->id] ?? null;
            }

            return $favorite;
        });

        return view('homestead.favorites', [
            'favorites' => $favorites,
            'activeType' => $type,
            'typeFilters' => HomesteadConfig::favoriteTypeFilters(),
            'labels' => [
                'title' => HomesteadConfig::favoriteLabel('title'),
                'description' => HomesteadConfig::favoriteLabel('description'),
                'empty_message' => HomesteadConfig::favoriteLabel('empty_message'),
                'empty_hint' => HomesteadConfig::favoriteLabel('empty_hint'),
            ],
        ]);
    }

    /**
     * Toggle favorite status for a target.
     *
     * @param  \Illuminate\Http\Request                  $request
     * @param  \App\Services\Homestead\FavoriteService   $service
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function postToggle(Request $request, FavoriteService $service)
    {
        $request->validate([
            'ref_type' => 'required|in:room,house,character',
            'ref_id' => 'required|integer|min:1',
        ]);

        $result = $service->toggleFavorite(
            Auth::user(),
            $request->input('ref_type'),
            (int) $request->input('ref_id')
        );

        if ($result === false) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $service->errors()->getMessages()['error'][0] ?? 'Unable to update favorite.',
                ], 422);
            }

            $this->flashServiceErrors($service);
            return redirect()->back();
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        flash(HomesteadConfig::favoriteLabel('toggle_message'))->success();
        return redirect()->back();
    }
}
