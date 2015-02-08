<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRetsFieldsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'rets_fields',
            function ($table) {
                $table->engine = 'InnoDB';
                $table->string('id', 16)->unique();
                $table->string('resource', 250);
                $table->string('lookup_id', 36)->unsigned();
                $table->string('short', 250);
                $table->string('long', 250);
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
        Schema::drop('rets_fields');
    }

}
