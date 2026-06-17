<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('slot_interval_minutes');
            $table->unsignedSmallInteger('break_between_minutes')->default(0);
            $table->unsignedSmallInteger('max_clients_per_slot')->default(1);
            $table->unsignedSmallInteger('max_booking_days')->default(7);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('services');
    }
};
