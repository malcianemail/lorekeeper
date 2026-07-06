<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToShopStockTradeItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
            //
public function up()
{
    Schema::table('shop_stock_trade_items', function (Blueprint $table) {
        $table->integer('shop_stock_id')->unsigned()->after('id');
        $table->integer('item_id')->unsigned()->after('shop_stock_id');
        $table->integer('quantity')->unsigned()->default(1)->after('item_id');
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
            //
public function down()
{
    Schema::table('shop_stock_trade_items', function (Blueprint $table) {
        $table->dropColumn(['shop_stock_id', 'item_id', 'quantity']);
    });
}
}
