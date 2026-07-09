<?php

namespace App\Services\Item;

use App\Services\Service;
use App\Services\Homestead\HomesteadConfig;
use App\Services\Item\Concerns\ActivatesSlotItem;

class HouseSlotService extends Service
{
    use ActivatesSlotItem;

    /**
     * Get the item tag handled by this service.
     *
     * @return string
     */
    protected function slotTag()
    {
        return HomesteadConfig::slotTag('outdoor');
    }
}
