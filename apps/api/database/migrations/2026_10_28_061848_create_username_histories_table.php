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
        Schema::create('username_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('username', 39);
            $table->timestamp('released_at')->useCurrent(); // lúc user đổi username
            $table->timestamp('available_at');              // lúc username có thể đăng ký lại
            $table->timestamps();

            // Index để query nhanh: username + available_at
            $table->index(['username', 'available_at']);
            // Unique constraint: một user không thể có 2 record cùng username cùng lúc
            $table->unique(['user_id', 'username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('username_histories');
    }
};