<?php

namespace App\Http\Controllers\Admin\Homestead;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Homestead\Concerns\FlashesServiceErrors;
use App\Services\Homestead\FeaturedService;
use App\Services\Homestead\HomesteadConfig;
use App\Models\Homestead\FeaturedItem;

class FeaturedController extends Controller
{
    use FlashesServiceErrors;

    /*
    |--------------------------------------------------------------------------
    | Admin Featured Controller
    |--------------------------------------------------------------------------
    |
    | Moderator management for the featured showcase.
    |
    */

    /**
     * Shows the featured management index.
     *
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getIndex(FeaturedService $service, $type = null)
    {
        $featuredType = $this->resolveFeaturedRouteType($type);

        return view('admin.homestead.featured.index', [
            'featured' => $service->getAdminFeatured($featuredType),
            'activeType' => $type,
            'labels' => [
                'title' => HomesteadConfig::featuredLabel('admin_title'),
                'description' => HomesteadConfig::featuredLabel('admin_description'),
            ],
        ]);
    }

    /**
     * Shows the create featured form.
     *
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getCreateFeatured(Request $request, FeaturedService $service)
    {
        $featured = new FeaturedItem;
        if ($request->get('type')) {
            $featured->type = $this->resolveFeaturedRouteType($request->get('type')) ?: $request->get('type');
        }

        return view('admin.homestead.featured.create_edit', [
            'featured' => $featured,
            'activeType' => $request->get('type'),
            'rooms' => $service->getFeatureableRooms(),
            'houses' => $service->getFeatureableHouses(),
            'characters' => $service->getFeatureableCharacters(),
        ]);
    }

    /**
     * Shows the edit featured form.
     *
     * @param  int                                     $id
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getEditFeatured($id, FeaturedService $service)
    {
        $featured = $service->getAdminFeaturedEntry($id);
        if (!$featured) {
            abort(404);
        }

        return view('admin.homestead.featured.create_edit', [
            'featured' => $featured,
            'activeType' => null,
            'rooms' => $service->getFeatureableRooms(),
            'houses' => $service->getFeatureableHouses(),
            'characters' => $service->getFeatureableCharacters(),
        ]);
    }

    /**
     * Creates a featured entry.
     *
     * @param  \Illuminate\Http\Request                 $request
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postCreateFeatured(Request $request, FeaturedService $service)
    {
        $request->validate(FeaturedItem::$createRules);

        if ($service->createFeatured($request->only([
            'type', 'ref_id', 'note', 'featured_order', 'is_active',
        ]))) {
            flash(HomesteadConfig::featuredLabel('created_message'))->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->to('admin/homestead/featured');
    }

    /**
     * Updates a featured entry.
     *
     * @param  \Illuminate\Http\Request                 $request
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @param  int                                        $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEditFeatured(Request $request, FeaturedService $service, $id)
    {
        $featured = FeaturedItem::find($id);
        if (!$featured) {
            abort(404);
        }

        $request->validate(FeaturedItem::$updateRules);

        if ($service->updateFeatured($featured, $request->only([
            'note', 'featured_order', 'is_active',
        ]))) {
            flash(HomesteadConfig::featuredLabel('updated_message'))->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->to('admin/homestead/featured');
    }

    /**
     * Toggles whether a featured entry is active.
     *
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @param  int                                        $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postToggleFeatured(FeaturedService $service, $id)
    {
        $featured = FeaturedItem::find($id);
        if (!$featured) {
            abort(404);
        }

        if ($service->toggleFeaturedActive($featured)) {
            flash(HomesteadConfig::featuredLabel('toggle_active_message'))->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Deletes a featured entry.
     *
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @param  int                                        $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postDeleteFeatured(FeaturedService $service, $id)
    {
        $featured = FeaturedItem::find($id);
        if (!$featured) {
            abort(404);
        }

        if ($service->deleteFeatured($featured)) {
            flash(HomesteadConfig::featuredLabel('deleted_message'))->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->to('admin/homestead/featured');
    }

    /**
     * Saves featured display order.
     *
     * @param  \Illuminate\Http\Request                 $request
     * @param  \App\Services\Homestead\FeaturedService  $service
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postSortFeatured(Request $request, FeaturedService $service)
    {
        $sort = $request->input('sort');
        $orderedIds = $sort ? explode(',', $sort) : [];

        if ($service->sortFeatured($orderedIds)) {
            flash(HomesteadConfig::featuredLabel('sorted_message'))->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->to('admin/homestead/featured');
    }

    /**
     * Map featured route segment to stored type.
     *
     * @param  string|null  $type
     * @return string|null
     */
    protected function resolveFeaturedRouteType($type)
    {
        $map = [
            'rooms' => FeaturedItem::TYPE_ROOM,
            'houses' => FeaturedItem::TYPE_HOUSE,
            'characters' => FeaturedItem::TYPE_CHARACTER,
            FeaturedItem::TYPE_ROOM => FeaturedItem::TYPE_ROOM,
            FeaturedItem::TYPE_HOUSE => FeaturedItem::TYPE_HOUSE,
            FeaturedItem::TYPE_CHARACTER => FeaturedItem::TYPE_CHARACTER,
        ];

        return $type ? ($map[$type] ?? null) : null;
    }
}
