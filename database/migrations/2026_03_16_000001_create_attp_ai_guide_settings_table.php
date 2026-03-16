<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attp_ai_guide_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('ATTP AI Guide');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('tawk_property_id')->nullable();
            $table->string('tawk_widget_id')->nullable();
            $table->boolean('show_to_authenticated_only')->default(true);
            $table->boolean('show_to_guests')->default(false);
            $table->json('targeted_user_roles')->nullable();
            $table->text('welcome_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attp_ai_guide_settings');
    }
};
