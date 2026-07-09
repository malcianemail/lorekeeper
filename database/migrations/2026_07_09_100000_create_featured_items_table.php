<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeaturedItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('featured_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 32);
            $table->unsignedInteger('ref_id');
            $table->unsignedInteger('owner_user_id')->nullable();
            $table->text('note')->nullable();
            $table->integer('featured_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'ref_id']);
            $table->index(['is_active', 'featured_order']);
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('featured_items');
    }
}
