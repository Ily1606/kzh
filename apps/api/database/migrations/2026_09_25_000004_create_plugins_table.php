<?php

use App\Enums\PluginStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('license');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', array_column(PluginStatus::cases(), 'value'))->default(PluginStatus::Pending->value);
            $table->string('source_link', 2048);
            $table->unsignedInteger('star_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name']);
            $table->index('status');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
