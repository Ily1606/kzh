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
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plugin_id', 'parent_comment_id', 'created_at']);
            $table->index(['parent_comment_id', 'created_at']);
            $table->index('author_id');
        });

        // Self-referential FK has to be added here, not inline above: Laravel
        // emits `add primary key` AFTER the foreign key constraints, so an inline
        // FK would reference `id` while it still has no unique constraint and
        // PostgreSQL would reject the whole migration with 42830.
        //
        // `cascade` rather than `set null`: a deleted root takes its reply tree
        // with it. `set null` would promote the children to root comments, which
        // splits one thread into several and inflates `meta.total` on the list
        // endpoint. It only fires on a hard delete — a soft delete leaves the
        // replies in place, which is what the current soft-delete flow wants.
        Schema::table('comments', function (Blueprint $table): void {
            $table->foreign('parent_comment_id')
                ->references('id')
                ->on('comments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
