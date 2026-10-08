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
        Schema::create('plugin_daily_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('plugin_id');
            $table->date('date');
            $table->integer('views_count')->default(0);
            $table->timestamps();

            $table->unique(['plugin_id', 'date']);
            $table->foreign('plugin_id')->references('id')->on('plugins')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_daily_views');
    }
};
