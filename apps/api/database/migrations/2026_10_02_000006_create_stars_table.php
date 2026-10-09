<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stars', function (Blueprint $table): void {
            $table->foreignUuid('plugin_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Composite primary key instead of a surrogate `id`. It is the whole
            // mechanism behind idempotent starring: the row *is* the fact that
            // this user starred this plugin, so there is nothing to insert twice.
            // A second `starred: true` for the same pair violates this constraint,
            // which `insertOrIgnore` turns into "no rows affected" rather than a
            // 500. See StarRepository::insertIgnore().
            $table->primary(['plugin_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stars');
    }
};
