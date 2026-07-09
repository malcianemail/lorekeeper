<?php

namespace App\Services\Homestead\Concerns;

use App\Models\Homestead\FeaturedItem;
use App\Models\Homestead\HomesteadFavorite;

trait CleansHomesteadReferences
{
    /**
     * Remove favorites and featured entries that point at a homestead subject.
     *
     * @param  string  $type
     * @param  int     $refId
     * @return void
     */
    protected function cleanupHomesteadReferences($type, $refId)
    {
        HomesteadFavorite::query()
            ->forTarget($type, $refId)
            ->delete();

        FeaturedItem::query()
            ->where('type', $type)
            ->where('ref_id', $refId)
            ->delete();
    }
}
