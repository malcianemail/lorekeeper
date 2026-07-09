<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddActivatedQuantityToUserItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_items', function (Blueprint $table) {
            $table->integer('activated_quantity')->unsigned()->default(0)->after('count');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_items', function (Blueprint $table) {
            $table->dropColumn('activated_quantity');
        });
    }
}
