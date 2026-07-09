<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHomesteadPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('room_placements', function (Blueprint $table) {
            $table->index(['room_save_id', 'z_index'], 'room_placements_room_z_index');
        });

        Schema::table('room_saves', function (Blueprint $table) {
            $table->index(['user_id', 'room_type'], 'room_saves_user_room_type_index');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->index(
                ['is_homestead_item', 'placement_type', 'homestead_room_type'],
                'items_homestead_placeable_index'
            );
        });

        Schema::table('user_items', function (Blueprint $table) {
            $table->index(['user_id', 'item_id'], 'user_items_user_item_index');
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
            $table->dropIndex('room_placements_room_z_index');
        });

        Schema::table('room_saves', function (Blueprint $table) {
            $table->dropIndex('room_saves_user_room_type_index');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_homestead_placeable_index');
        });

        Schema::table('user_items', function (Blueprint $table) {
            $table->dropIndex('user_items_user_item_index');
        });
    }
}
