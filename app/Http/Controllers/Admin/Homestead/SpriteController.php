<?php

namespace App\Http\Controllers\Admin\Homestead;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Homestead\Concerns\FlashesServiceErrors;
use App\Models\Character\Character;
use App\Models\Character\CharacterSprite;
use App\Services\Character\CharacterSpriteService;

class SpriteController extends Controller
{
    use FlashesServiceErrors;

    /**
     * Shows the admin sprite index.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getIndex(Request $request)
    {
        $sprites = CharacterSprite::query()
            ->with(['character.user'])
            ->when($request->get('character'), function ($query) use ($request) {
                $query->whereHas('character', function ($characterQuery) use ($request) {
                    $characterQuery->where('slug', 'LIKE', '%' . $request->get('character') . '%')
                        ->orWhere('number', 'LIKE', '%' . $request->get('character') . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.homestead.sprites.index', [
            'sprites' => $sprites,
        ]);
    }

    /**
     * Shows sprite management for a character.
     *
     * @param  string  $slug
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getCharacterSprites($slug)
    {
        $character = Character::myo(0)->where('slug', $slug)->first();
        if (!$character) {
            abort(404);
        }

        $service = new CharacterSpriteService;

        return view('admin.homestead.sprites.character', [
            'character' => $character,
            'sprites' => $character->sprites()->orderBy('sort', 'DESC')->get(),
            'slots' => $service->getSpriteSlotSummary($character),
            'canManage' => true,
        ]);
    }

    /**
     * Upload a sprite for a character (admin).
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postCreateSprite(Request $request, CharacterSpriteService $service, $slug)
    {
        $character = Character::myo(0)->where('slug', $slug)->first();
        if (!$character) {
            abort(404);
        }

        $request->validate(CharacterSpriteService::$imageRules + [
            'name' => 'nullable|string|max:100',
        ]);

        if ($service->createSprite($request->only(['name', 'image']), $character, Auth::user())) {
            flash('Sprite uploaded successfully.')->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Update a sprite (admin).
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @param  int                                     $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEditSprite(Request $request, CharacterSpriteService $service, $slug, $id)
    {
        $sprite = $this->resolveSprite($slug, $id);
        $request->validate(CharacterSpriteService::$updateRules);

        if ($service->updateSprite($request->only(['name', 'image']), $sprite, Auth::user())) {
            flash('Sprite updated successfully.')->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Set the active sprite (admin).
     *
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @param  int                                     $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postActiveSprite(CharacterSpriteService $service, $slug, $id)
    {
        $sprite = $this->resolveSprite($slug, $id);

        if ($service->setActiveSprite($sprite, Auth::user())) {
            flash('Active sprite updated.')->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Delete a sprite (admin).
     *
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @param  int                                     $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postDeleteSprite(CharacterSpriteService $service, $slug, $id)
    {
        $sprite = $this->resolveSprite($slug, $id);

        if ($service->deleteSprite($sprite, Auth::user())) {
            flash('Sprite deleted successfully.')->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Sort sprites for a character (admin).
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postSortSprites(Request $request, CharacterSpriteService $service, $slug)
    {
        $character = Character::myo(0)->where('slug', $slug)->first();
        if (!$character) {
            abort(404);
        }

        $sort = $request->input('sort');
        $spriteIds = $sort ? explode(',', $sort) : [];

        if ($service->sortSprites($spriteIds, $character, Auth::user())) {
            flash('Sprite order saved.')->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Resolve a sprite belonging to a character slug.
     *
     * @param  string  $slug
     * @param  int     $id
     * @return \App\Models\Character\CharacterSprite
     */
    protected function resolveSprite($slug, $id)
    {
        $character = Character::myo(0)->where('slug', $slug)->first();
        if (!$character) {
            abort(404);
        }

        $sprite = CharacterSprite::where('character_id', $character->id)->where('id', $id)->first();
        if (!$sprite) {
            abort(404);
        }

        return $sprite;
    }
}
