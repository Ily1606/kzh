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
        Schema::create('comment_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            
            // Assuming comments and plugins also use UUIDs.
            // If they use standard auto-increment IDs, this should be foreignId().
            // Let's use foreignUuid() since most tables in this project seem to use UUIDs based on earlier context.
            $table->foreignUuid('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plugin_id')->constrained()->cascadeOnDelete();
            
            $table->text('reason');
            
            $table->timestamps();
            $table->softDeletes();
            
            // A user can only report a specific comment once
            $table->unique(['user_id', 'comment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
    }
};
