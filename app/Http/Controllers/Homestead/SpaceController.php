<?php

namespace App\Http\Controllers\Homestead;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Homestead\Concerns\FlashesServiceErrors;
use App\Http\Controllers\Homestead\Concerns\ResolvesHomesteadSpaceType;
use App\Services\Homestead\HomesteadConfig;
use App\Services\Homestead\RoomManager;
use App\Models\Homestead\RoomSave;

class SpaceController extends Controller
{
    use FlashesServiceErrors;
    use ResolvesHomesteadSpaceType;

    /*
    |--------------------------------------------------------------------------
    | Homestead Space Controller
    |--------------------------------------------------------------------------
    |
    | Shared CRUD for indoor rooms and outdoor houses.
    |
    */

    /**
     * Shows the user's spaces list.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  \App\Services\Homestead\RoomManager  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getIndex(Request $request, RoomManager $service)
    {
        $roomType = $this->resolveSpaceType($request);
        $labels = HomesteadConfig::spaceLabels($roomType);

        return view('homestead.spaces', [
            'labels' => $labels,
            'roomType' => $roomType,
            'spaces' => RoomSave::ownedBy(Auth::id())
                ->ofType($roomType)
                ->select('id', 'name', 'room_type', 'created_at')
                ->orderBy('name')
                ->get(),
            'slots' => $service->getSlotSummary(Auth::user(), $roomType),
        ]);
    }

    /**
     * Gets the space creation modal.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  \App\Services\Homestead\RoomManager  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getCreate(Request $request, RoomManager $service)
    {
        $roomType = $this->resolveSpaceType($request);
        $slots = $service->getSlotSummary(Auth::user(), $roomType);
        if (!$slots['can_create']) {
            abort(403);
        }

        return view('homestead._create_edit_space', [
            'labels' => HomesteadConfig::spaceLabels($roomType),
            'space' => new RoomSave(['room_type' => $roomType]),
        ]);
    }

    /**
     * Creates a space.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  \App\Services\Homestead\RoomManager  $service
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postCreate(Request $request, RoomManager $service)
    {
        $roomType = $this->resolveSpaceType($request);
        $labels = HomesteadConfig::spaceLabels($roomType);
        $request->validate(RoomSave::$createRules);

        if ($service->createRoom($request->only(['name']), Auth::user(), $roomType)) {
            flash($labels['created_message'])->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Gets the space editing modal.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  int                                    $id
     * @param  \App\Services\Homestead\RoomManager    $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getEdit(Request $request, $id, RoomManager $service)
    {
        return view('homestead._create_edit_space', [
            'labels' => HomesteadConfig::spaceLabels($this->resolveSpaceType($request)),
            'space' => $this->resolveOwnedSpace($request, $id, $service),
        ]);
    }

    /**
     * Updates a space.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  \App\Services\Homestead\RoomManager  $service
     * @param  int                                    $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postEdit(Request $request, RoomManager $service, $id)
    {
        $this->resolveOwnedSpace($request, $id, $service);

        $labels = HomesteadConfig::spaceLabels($this->resolveSpaceType($request));
        $request->validate(RoomSave::$updateRules);

        if ($service->updateRoom($request->only(['name']) + ['room_id' => $id], Auth::user())) {
            flash($labels['updated_message'])->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Gets the space deletion modal.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  int                                    $id
     * @param  \App\Services\Homestead\RoomManager  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getDelete(Request $request, $id, RoomManager $service)
    {
        return view('homestead._delete_space', [
            'labels' => HomesteadConfig::spaceLabels($this->resolveSpaceType($request)),
            'space' => $this->resolveOwnedSpace($request, $id, $service),
        ]);
    }

    /**
     * Deletes a space.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  \App\Services\Homestead\RoomManager  $service
     * @param  int                                    $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postDelete(Request $request, RoomManager $service, $id)
    {
        $this->resolveOwnedSpace($request, $id, $service);

        $labels = HomesteadConfig::spaceLabels($this->resolveSpaceType($request));

        if ($service->deleteRoom(['room_id' => $id], Auth::user())) {
            flash($labels['deleted_message'])->success();
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }
}
