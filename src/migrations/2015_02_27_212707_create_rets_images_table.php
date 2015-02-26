<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRetsImagesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'rets_images',
            function ($table) {
                $table->engine = 'InnoDB';
                $table->bigIncrements('id');
                $table->string('name', 250);
                $table->string('path', 250);
                $table->boolean('main')->nullable();
                $table->integer('position');
                $table->string('type', 36);
                $table->integer('size')->default(0);
                $table->string('parent_type', 36);
                $table->integer('parent_id');
                $table->text('description')->nullable();
                $table->timestamps();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::drop('rets_images');
    }

}
