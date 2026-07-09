<?php

namespace App\Models\Homestead;

use App\Models\Model;
use App\Models\User\User;

class HomesteadFavorite extends Model
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
        'user_id', 'ref_type', 'ref_id',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'homestead_favorites';

    /**********************************************************************************************

        RELATIONS

    **********************************************************************************************/

    /**
     * Get the user who favorited this item.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**********************************************************************************************

        SCOPES

    **********************************************************************************************/

    /**
     * Scope a query to a ref type.
     */
    public function scopeOfType($query, $type)
    {
        if (!$type) {
            return $query;
        }

        return $query->where('ref_type', $type);
    }

    /**
     * Scope a query to a specific target.
     */
    public function scopeForTarget($query, $type, $refId)
    {
        return $query->where('ref_type', $type)->where('ref_id', $refId);
    }
}
