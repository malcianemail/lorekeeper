<?php

namespace App\Models\Character;

use App\Models\Model;
use App\Models\Homestead\RoomLayout;

class CharacterSprite extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'character_id', 'name', 'sort', 'has_image', 'extension',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'character_sprites';

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
     * Get the character this sprite belongs to.
     */
    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * Get room layouts using this sprite.
     */
    public function roomLayouts()
    {
        return $this->hasMany(RoomLayout::class);
    }

    /**********************************************************************************************

        ACCESSORS

    **********************************************************************************************/

    /**
     * Gets the display name for the sprite.
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->name ?: 'Sprite #' . $this->id;
    }

    /**
     * Gets the file directory containing the sprite image.
     *
     * @return string
     */
    public function getImageDirectoryAttribute()
    {
        return 'images/character-sprites';
    }

    /**
     * Gets the path to the file directory containing the sprite image.
     *
     * @return string
     */
    public function getImagePathAttribute()
    {
        return public_path($this->imageDirectory);
    }

    /**
     * Gets the file name of the sprite image.
     *
     * @return string
     */
    public function getImageFileNameAttribute()
    {
        return $this->id . '-sprite.' . $this->extension;
    }

    /**
     * Gets the URL of the sprite image.
     *
     * @return string|null
     */
    public function getImageUrlAttribute()
    {
        if (!$this->has_image || !$this->extension) {
            return null;
        }

        return asset($this->imageDirectory . '/' . $this->imageFileName);
    }
}
