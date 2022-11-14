<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('joints', function(Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('technical_location');
            $table->integer('equipment');
            $table->string('description');
            $table->string('track_number');
            $table->string('from_kilometers');
            $table->point('coordinates', 4326)->nullable();
            $table->string('position_location');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('joints');
    }
};
