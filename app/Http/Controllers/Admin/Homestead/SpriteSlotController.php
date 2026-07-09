<?php

namespace App\Http\Controllers\Admin\Homestead;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Homestead\Concerns\FlashesServiceErrors;
use App\Models\Character\Character;
use App\Services\Character\CharacterSpriteService;
use App\Services\Homestead\HomesteadConfig;

class SpriteSlotController extends Controller
{
    use FlashesServiceErrors;

    /**
     * Shows the sprite slot management index.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getIndex(Request $request)
    {
        $service = new CharacterSpriteService;
        $characters = Character::query()
            ->myo(0)
            ->with('user')
            ->withCount('sprites')
            ->when($request->get('character'), function ($query) use ($request) {
                $query->where(function ($characterQuery) use ($request) {
                    $characterQuery->where('slug', 'LIKE', '%' . $request->get('character') . '%')
                        ->orWhere('number', 'LIKE', '%' . $request->get('character') . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $characters->getCollection()->transform(function ($character) use ($service) {
            $character->slot_summary = $service->getSpriteSlotSummary($character);

            return $character;
        });

        return view('admin.homestead.sprite_slots.index', [
            'characters' => $characters,
            'baseSlots' => HomesteadConfig::baseSpriteSlots(),
        ]);
    }

    /**
     * Update sprite slot limits for a character.
     *
     * @param  \Illuminate\Http\Request              $request
     * @param  \App\Services\Character\CharacterSpriteService  $service
     * @param  int                                     $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEditSlots(Request $request, CharacterSpriteService $service, $id)
    {
        $character = Character::myo(0)->find($id);
        if (!$character) {
            abort(404);
        }

        $request->validate([
            'max_sprite_slots' => 'required|integer|min:' . HomesteadConfig::baseSpriteSlots() . '|max:999',
        ]);

        if ($service->updateMaxSpriteSlots($character, $request->input('max_sprite_slots'), Auth::user())) {
            flash('Sprite slot limit updated successfully.')->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }
}
