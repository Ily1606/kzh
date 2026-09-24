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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('id', 36)->change();
            $table->boolean('isActive')->default(true);
            $table->string('avatarLink')->nullable();
            $table->string('githubName')->nullable();
            $table->string('githubLink')->nullable();
            $table->boolean('isDeleted')->default(false);
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->string('user_id', 36)->nullable()->change();
            $table->index('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->index('user_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'isActive',
                'avatarLink',
                'githubName',
                'githubLink',
                'isDeleted',
            ]);
            $table->unsignedBigInteger('id')->change();
        });
    }
};
