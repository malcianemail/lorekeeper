<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHomesteadRoomTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('room_saves', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->string('name');
            $table->enum('room_type', ['indoor', 'outdoor'])->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('room_layouts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('room_save_id')->unsigned()->unique();
            $table->integer('character_id')->unsigned()->nullable()->default(null);
            $table->integer('character_sprite_id')->unsigned()->nullable()->default(null);
            $table->decimal('sprite_position_x', 10, 2)->default(0);
            $table->decimal('sprite_position_y', 10, 2)->default(0);
            $table->integer('wallpaper_item_id')->unsigned()->nullable()->default(null);
            $table->integer('flooring_item_id')->unsigned()->nullable()->default(null);
            $table->integer('roof_item_id')->unsigned()->nullable()->default(null);
            $table->integer('exterior_wall_item_id')->unsigned()->nullable()->default(null);
            $table->timestamps();

            $table->foreign('room_save_id')->references('id')->on('room_saves');
            $table->foreign('character_id')->references('id')->on('characters');
            $table->foreign('character_sprite_id')->references('id')->on('character_sprites');
            $table->foreign('wallpaper_item_id')->references('id')->on('items');
            $table->foreign('flooring_item_id')->references('id')->on('items');
            $table->foreign('roof_item_id')->references('id')->on('items');
            $table->foreign('exterior_wall_item_id')->references('id')->on('items');
        });

        Schema::create('room_placements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('room_save_id')->unsigned()->index();
            $table->integer('item_id')->unsigned()->index();
            $table->integer('user_item_id')->unsigned()->nullable()->default(null);
            $table->decimal('x', 10, 2)->default(0);
            $table->decimal('y', 10, 2)->default(0);
            $table->decimal('width', 10, 2)->nullable()->default(null);
            $table->decimal('height', 10, 2)->nullable()->default(null);
            $table->integer('z_index')->default(0);
            $table->timestamps();

            $table->foreign('room_save_id')->references('id')->on('room_saves');
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('user_item_id')->references('id')->on('user_items');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('room_placements');
        Schema::dropIfExists('room_layouts');
        Schema::dropIfExists('room_saves');
    }
}
