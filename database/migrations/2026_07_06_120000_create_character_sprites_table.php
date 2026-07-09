<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCharacterSpritesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('character_sprites', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('character_id')->unsigned()->index();
            $table->integer('sort')->unsigned()->default(0);
            $table->boolean('has_image')->default(0);
            $table->string('extension', 10)->nullable()->default(null);
            $table->timestamps();

            $table->foreign('character_id')->references('id')->on('characters');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('character_sprites');
    }
}
