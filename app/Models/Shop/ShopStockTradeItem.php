<?php

namespace App\Models\Shop;

use App\Models\Model;

class ShopStockTradeItem extends Model
{
    protected $table = 'shop_stock_trade_items';

    protected $fillable = [
        'shop_stock_id', 'item_id', 'quantity'
    ];

    public function stock()
    {
        return $this->belongsTo('App\Models\Shop\ShopStock', 'shop_stock_id');
    }

    public function item()
    {
        return $this->belongsTo('App\Models\Item\Item', 'item_id');
    }
}
