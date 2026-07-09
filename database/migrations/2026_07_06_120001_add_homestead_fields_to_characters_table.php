<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHomesteadFieldsToCharactersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->integer('max_sprite_slots')->unsigned()->default(1)->after('sort');
            $table->integer('active_sprite_id')->unsigned()->nullable()->default(null)->after('max_sprite_slots');

            $table->foreign('active_sprite_id')->references('id')->on('character_sprites');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['active_sprite_id']);
            $table->dropColumn(['max_sprite_slots', 'active_sprite_id']);
        });
    }
}
