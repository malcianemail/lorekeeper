<?php

namespace App\Http\Controllers\Homestead;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Homestead\Concerns\FlashesServiceErrors;
use App\Http\Controllers\Homestead\Concerns\ResolvesHomesteadSpaceType;
use App\Services\Homestead\RoomManager;

class HomesteadEditorController extends Controller
{
    use FlashesServiceErrors;
    use ResolvesHomesteadSpaceType;

    /*
    |--------------------------------------------------------------------------
    | Homestead Editor Controller
    |--------------------------------------------------------------------------
    |
    | Shared decoration editor for indoor rooms and outdoor houses.
    |
    */

    /**
     * Shows the homestead editor.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  int                                    $id
     * @param  \App\Services\Homestead\RoomManager  $service
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function getEditor(Request $request, $id, RoomManager $service)
    {
        $room = $this->resolveOwnedSpace($request, $id, $service);

        return view('homestead.homestead_editor', $service->getEditorViewData(Auth::user(), $room));
    }

    /**
     * Saves homestead editor placements.
     *
     * @param  \Illuminate\Http\Request             $request
     * @param  int                                    $id
     * @param  \App\Services\Homestead\RoomManager  $service
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postSave(Request $request, $id, RoomManager $service)
    {
        $room = $this->resolveOwnedSpace($request, $id, $service);
        $settings = $service->getEditorSettings($room->room_type);

        $placements = json_decode($request->input('placements', '[]'), true);
        if (!$this->isJsonList($placements)) {
            flash('Invalid placement data.')->error();
            return redirect()->back();
        }

        $layout = json_decode($request->input('layout', '{}'), true);
        if (!$this->isJsonMap($layout)) {
            flash('Invalid layout data.')->error();
            return redirect()->back();
        }

        if ($service->saveEditorState([
            'placements' => $placements,
            'layout' => $layout,
        ], Auth::user(), $room)) {
            flash($settings['save_message'])->success();

            $dropped = $service->getLastDroppedPlacementCount();
            if ($dropped > 0) {
                flash('Removed ' . $dropped . ' placement' . ($dropped === 1 ? '' : 's') . ' that are no longer valid for this space.')->warning();
            }
        } else {
            $this->flashServiceErrors($service);
        }

        return redirect()->back();
    }

    /**
     * Whether decoded JSON is a sequential list (JSON array).
     *
     * @param  mixed  $value
     * @return bool
     */
    private function isJsonList($value)
    {
        if (!is_array($value)) {
            return false;
        }

        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * Whether decoded JSON is a string-keyed map (JSON object).
     *
     * @param  mixed  $value
     * @return bool
     */
    private function isJsonMap($value)
    {
        if (!is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
