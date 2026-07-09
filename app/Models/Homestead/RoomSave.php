<?php

namespace App\Models\Homestead;

use App\Models\Model;
use App\Models\User\User;
use App\Models\Character\Character;
use App\Models\Character\CharacterSprite;
use App\Models\Item\Item;
use App\Models\User\UserItem;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomSave extends Model
{
    use SoftDeletes;

    const TYPE_INDOOR = 'indoor';
    const TYPE_OUTDOOR = 'outdoor';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'name', 'room_type',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'room_saves';

    /**
     * Whether the model contains timestamps to be saved and updated.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Validation rules for creation.
     *
     * @var array
     */
    public static $createRules = [
        'name' => 'required|between:3,100',
    ];

    /**
     * Validation rules for updating.
     *
     * @var array
     */
    public static $updateRules = [
        'name' => 'required|between:3,100',
    ];

    /**********************************************************************************************

        RELATIONS

    **********************************************************************************************/

    /**
     * Get the owner of the room save.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the layout for the room save.
     */
    public function layout()
    {
        return $this->hasOne(RoomLayout::class);
    }

    /**
     * Get the furniture placements for the room save.
     */
    public function placements()
    {
        return $this->hasMany(RoomPlacement::class);
    }

    /**********************************************************************************************

        SCOPES

    **********************************************************************************************/

    /**
     * Scope a query to indoor rooms.
     */
    public function scopeIndoor($query)
    {
        return $query->where('room_type', self::TYPE_INDOOR);
    }

    /**
     * Scope a query to outdoor houses.
     */
    public function scopeOutdoor($query)
    {
        return $query->where('room_type', self::TYPE_OUTDOOR);
    }

    /**
     * Scope a query to a specific user.
     */
    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to a specific space type.
     */
    public function scopeOfType($query, $roomType)
    {
        return $query->where('room_type', $roomType);
    }

    /**********************************************************************************************

        ACCESSORS

    **********************************************************************************************/

    /**
     * Whether this save is an indoor room.
     *
     * @return bool
     */
    public function getIsRoomAttribute()
    {
        return $this->room_type === self::TYPE_INDOOR;
    }

    /**
     * Whether this save is an outdoor house.
     *
     * @return bool
     */
    public function getIsHouseAttribute()
    {
        return $this->room_type === self::TYPE_OUTDOOR;
    }

    /**
     * Gets the URL of the room's page.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return url('homestead/' . $this->list_segment);
    }

    /**
     * Gets the URL of the shared homestead editor for this space.
     *
     * @return string
     */
    public function getEditorUrlAttribute()
    {
        return url('homestead/' . $this->list_segment . '/' . $this->id . '/editor');
    }

    /**
     * Gets the list URL segment for this space (rooms or houses).
     *
     * @return string
     */
    public function getListSegmentAttribute()
    {
        return \App\Services\Homestead\HomesteadConfig::listSegment($this->room_type);
    }
}
