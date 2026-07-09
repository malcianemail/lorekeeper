<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHomesteadFieldsToItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_homestead_item')->default(0)->after('is_released');
            $table->string('placement_type', 50)->nullable()->default(null)->after('is_homestead_item');
            $table->string('homestead_room_type', 50)->nullable()->default(null)->after('placement_type');
            $table->integer('default_width')->unsigned()->nullable()->default(null)->after('homestead_room_type');
            $table->integer('default_height')->unsigned()->nullable()->default(null)->after('default_width');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'is_homestead_item',
                'placement_type',
                'homestead_room_type',
                'default_width',
                'default_height',
            ]);
        });
    }
}
