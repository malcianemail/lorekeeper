<?php

namespace App\Models\Homestead;

use App\Models\Model;
use App\Models\Item\Item;
use App\Models\User\UserItem;

class RoomPlacement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'room_save_id', 'item_id', 'character_sprite_id', 'user_item_id',
        'x', 'y', 'width', 'height', 'z_index',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'room_placements';

    /**
     * Whether the model contains timestamps to be saved and updated.
     *
     * @var bool
     */
    public $timestamps = true;

    /**********************************************************************************************

        RELATIONS

    **********************************************************************************************/

    /**
     * Get the room save this placement belongs to.
     */
    public function roomSave()
    {
        return $this->belongsTo(RoomSave::class);
    }

    /**
     * Get the placed item definition.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the user inventory stack this placement was sourced from.
     */
    public function userItem()
    {
        return $this->belongsTo(UserItem::class);
    }

    /**
     * Get the placed character sprite.
     */
    public function characterSprite()
    {
        return $this->belongsTo('App\Models\Character\CharacterSprite', 'character_sprite_id');
    }
}
