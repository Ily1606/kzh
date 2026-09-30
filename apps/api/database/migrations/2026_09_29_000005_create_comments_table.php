<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plugin_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('parent_comment_id')->nullable();
            $table->text('content');
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Serves both list queries: root comments (parent_comment_id IS NULL)
            // and the direct replies of a single comment. Both filter by
            // plugin_id, filter out hidden rows and sort by created_at.
            $table->index(['plugin_id', 'parent_comment_id', 'created_at']);
            $table->index('author_id');
        });

        // Self-referential foreign key must be added after table creation
        // to avoid PostgreSQL constraint ordering issues
        Schema::table('comments', function (Blueprint $table): void {
            $table->foreign('parent_comment_id')
                ->references('id')
                ->on('comments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
