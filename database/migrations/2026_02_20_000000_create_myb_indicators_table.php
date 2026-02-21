<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('myb_indicators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('indicatorable_type')->nullable(); // Program, Project, Activity, SubActivity
            $table->uuid('indicatorable_id')->nullable();
            $table->string('name');
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['indicatorable_type', 'indicatorable_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('myb_indicators');
    }
};