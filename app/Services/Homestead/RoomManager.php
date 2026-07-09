<?php namespace App\Services\Homestead;

use App\Services\Service;
use App\Services\Homestead\Concerns\ManagesHomesteadSlots;
use App\Services\Homestead\Concerns\ManagesHomesteadSpaces;
use App\Services\Homestead\Concerns\ManagesHomesteadEditor;

class RoomManager extends Service
{
    use ManagesHomesteadSlots;
    use ManagesHomesteadSpaces;
    use ManagesHomesteadEditor;
    use \App\Services\Homestead\Concerns\ManagesHomesteadPreview;

    /*
    |--------------------------------------------------------------------------
    | Room Manager
    |--------------------------------------------------------------------------
    |
    | Facade for homestead slot, space CRUD, and editor operations.
    |
    */
}
