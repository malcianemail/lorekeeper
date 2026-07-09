<?php

namespace App\Models\Homestead;

use App\Models\Model;
use App\Models\User\User;
use App\Models\Character\Character;

class FeaturedItem extends Model
{
    const TYPE_ROOM = 'room';
    const TYPE_HOUSE = 'house';
    const TYPE_CHARACTER = 'character';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type', 'ref_id', 'owner_user_id', 'note', 'featured_order', 'is_active',
    ];

    /**
     * Attribute casting.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'featured_items';

    /**
     * Validation rules for creation.
     *
     * @var array
     */
    public static $createRules = [
        'type' => 'required|in:room,house,character',
        'ref_id' => 'required|integer|min:1',
        'note' => 'nullable|string|max:2000',
        'featured_order' => 'nullable|integer|min:0|max:9999',
        'is_active' => 'nullable|boolean',
    ];

    /**
     * Validation rules for updating.
     *
     * @var array
     */
    public static $updateRules = [
        'note' => 'nullable|string|max:2000',
        'featured_order' => 'nullable|integer|min:0|max:9999',
        'is_active' => 'nullable|boolean',
    ];

    /**********************************************************************************************

        RELATIONS

    **********************************************************************************************/

    /**
     * Get the owner user at the time of featuring.
     */
    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**********************************************************************************************

        SCOPES

    **********************************************************************************************/

    /**
     * Scope a query to active featured entries.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope a query to featured display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('featured_order')->orderBy('id');
    }

    /**
     * Scope a query to a featured type.
     */
    public function scopeOfType($query, $type)
    {
        if (!$type) {
            return $query;
        }

        return $query->where('type', $type);
    }

    /**********************************************************************************************

        ACCESSORS

    **********************************************************************************************/

    /**
     * Find the best showcase URL for a homestead subject.
     *
     * @param  string  $type
     * @param  int     $refId
     * @return string|null
     */
    public static function findUrlForRef($type, $refId)
    {
        $featured = static::query()
            ->where('type', $type)
            ->where('ref_id', $refId)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();

        return $featured ? $featured->url : null;
    }

    /**
     * Gets the public showcase detail URL.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return url('showcase/' . $this->id);
    }

    /**
     * Gets the human-readable type label.
     *
     * @return string
     */
    public function getTypeLabelAttribute()
    {
        return \App\Services\Homestead\HomesteadConfig::featuredTypeLabel($this->type);
    }
}
