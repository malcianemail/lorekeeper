<?php

namespace App\Http\Controllers\Homestead\Concerns;

use App\Services\Service;

trait FlashesServiceErrors
{
    /**
     * Flash validation errors from a service response.
     *
     * @param  \App\Services\Service  $service
     * @return void
     */
    protected function flashServiceErrors(Service $service)
    {
        foreach ($service->errors()->getMessages()['error'] ?? [] as $error) {
            flash($error)->error();
        }
    }
}
