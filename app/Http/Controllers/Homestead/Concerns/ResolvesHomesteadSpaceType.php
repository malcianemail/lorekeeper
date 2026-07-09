<?php

namespace App\Http\Controllers\Homestead\Concerns;

use Auth;
use Illuminate\Http\Request;
use App\Models\Homestead\RoomSave;
use App\Services\Homestead\HomesteadConfig;
use App\Services\Homestead\RoomManager;

trait ResolvesHomesteadSpaceType
{
    /**
     * Resolve indoor vs outdoor from the current request path.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function resolveSpaceType(Request $request)
    {
        return str_contains($request->path(), 'houses')
            ? RoomSave::TYPE_OUTDOOR
            : RoomSave::TYPE_INDOOR;
    }

    /**
     * Get UI labels for the current space type.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function spaceLabels(Request $request)
    {
        return HomesteadConfig::spaceLabels($this->resolveSpaceType($request));
    }

    /**
     * Get an owned space matching the request path type.
     *
     * @param  \Illuminate\Http\Request          $request
     * @param  int                                 $id
     * @param  \App\Services\Homestead\RoomManager $service
     * @return \App\Models\Homestead\RoomSave
     */
    protected function resolveOwnedSpace(Request $request, $id, RoomManager $service)
    {
        $roomType = $this->resolveSpaceType($request);
        $space = $service->getUserRoom($id, Auth::user(), $roomType);

        if (!$space) {
            abort(404);
        }

        return $space;
    }
}
