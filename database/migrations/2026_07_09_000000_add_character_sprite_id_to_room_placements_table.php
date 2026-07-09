<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCharacterSpriteIdToRoomPlacementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('room_placements', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('room_placements', function (Blueprint $table) {
            $table->integer('item_id')->unsigned()->nullable()->change();
            $table->integer('character_sprite_id')->unsigned()->nullable()->default(null)->after('item_id');

            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('character_sprite_id')->references('id')->on('character_sprites');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('room_placements', function (Blueprint $table) {
            $table->dropForeign(['character_sprite_id']);
            $table->dropColumn('character_sprite_id');
        });

        Schema::table('room_placements', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('room_placements', function (Blueprint $table) {
            $table->integer('item_id')->unsigned()->nullable(false)->change();

            $table->foreign('item_id')->references('id')->on('items');
        });
    }
}
