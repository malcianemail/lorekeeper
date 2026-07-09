<?php

namespace App\Http\Controllers\Characters;

use Auth;
use Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Character\Character;
use App\Models\Character\CharacterSprite;
use App\Services\Character\CharacterSpriteService;

class CharacterSpriteController extends Controller
{
    /**
     * @var \App\Models\Character\Character
     */
    protected $character;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::check()) {
                abort(404);
            }

            $slug = Route::current()->parameter('slug');
            $query = Character::myo(0)->where('slug', $slug);
            if (!Auth::user()->hasPower('manage_characters')) {
                $query->where('is_visible', 1);
            }

            $this->character = $query->first();
            if (!$this->character) {
                abort(404);
            }

            $this->character->updateOwner();

            return $next($request);
        });
    }

    /**
     * Upload a new sprite.
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postCreateSprite(Request $request, CharacterSpriteService $service, $slug)
    {
        $request->validate(CharacterSpriteService::$imageRules + [
            'name' => 'nullable|string|max:100',
        ]);

        if ($service->createSprite($request->only(['name', 'image']), $this->character, Auth::user())) {
            flash('Sprite uploaded successfully.')->success();
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }
        }

        return redirect()->back();
    }

    /**
     * Update a sprite.
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @param  int                                     $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEditSprite(Request $request, CharacterSpriteService $service, $slug, $id)
    {
        $request->validate(CharacterSpriteService::$updateRules);

        $sprite = $this->getOwnedSprite($id);
        if (!$sprite) {
            abort(404);
        }

        if ($service->updateSprite($request->only(['name', 'image']), $sprite, Auth::user())) {
            flash('Sprite updated successfully.')->success();
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }
        }

        return redirect()->back();
    }

    /**
     * Delete a sprite.
     *
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @param  int                                     $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postDeleteSprite(CharacterSpriteService $service, $slug, $id)
    {
        $sprite = $this->getOwnedSprite($id);
        if (!$sprite) {
            abort(404);
        }

        if ($service->deleteSprite($sprite, Auth::user())) {
            flash('Sprite deleted successfully.')->success();
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }
        }

        return redirect()->back();
    }

    /**
     * Set a sprite as active.
     *
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @param  int                                     $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postSetActiveSprite(CharacterSpriteService $service, $slug, $id)
    {
        $sprite = $this->getOwnedSprite($id);
        if (!$sprite) {
            abort(404);
        }

        if ($service->setActiveSprite($sprite, Auth::user())) {
            flash('Active sprite updated.')->success();
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }
        }

        return redirect()->back();
    }

    /**
     * Sort sprites.
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  string                                  $slug
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postSortSprites(Request $request, CharacterSpriteService $service, $slug)
    {
        $spriteIds = $request->input('sort');
        if (is_string($spriteIds)) {
            $spriteIds = array_filter(explode(',', $spriteIds));
        }

        if ($service->sortSprites($spriteIds, $this->character, Auth::user())) {
            flash('Sprite order saved.')->success();
        } else {
            foreach ($service->errors()->getMessages()['error'] as $error) {
                flash($error)->error();
            }
        }

        return redirect()->back();
    }

    /**
     * Get a sprite belonging to the current character.
     *
     * @param  int  $id
     * @return \App\Models\Character\CharacterSprite|null
     */
    protected function getOwnedSprite($id)
    {
        return CharacterSprite::where('id', $id)
            ->where('character_id', $this->character->id)
            ->first();
    }
}
