<?php

namespace App\Models\Homestead;

use App\Models\Model;
use App\Models\Character\Character;
use App\Models\Character\CharacterSprite;
use App\Models\Item\Item;

class RoomLayout extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'room_save_id', 'character_id', 'character_sprite_id',
        'sprite_position_x', 'sprite_position_y',
        'wallpaper_item_id', 'flooring_item_id', 'roof_item_id', 'exterior_wall_item_id',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'room_layouts';

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
     * Get the room save this layout belongs to.
     */
    public function roomSave()
    {
        return $this->belongsTo(RoomSave::class);
    }

    /**
     * Get the character placed in the room.
     */
    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * Get the sprite used for the placed character.
     */
    public function characterSprite()
    {
        return $this->belongsTo(CharacterSprite::class);
    }

    /**
     * Get the wallpaper item.
     */
    public function wallpaper()
    {
        return $this->belongsTo(Item::class, 'wallpaper_item_id');
    }

    /**
     * Get the flooring item.
     */
    public function flooring()
    {
        return $this->belongsTo(Item::class, 'flooring_item_id');
    }

    /**
     * Get the roof item.
     */
    public function roof()
    {
        return $this->belongsTo(Item::class, 'roof_item_id');
    }

    /**
     * Get the exterior wall item.
     */
    public function exteriorWall()
    {
        return $this->belongsTo(Item::class, 'exterior_wall_item_id');
    }
}
