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
            $table->uuid('id')->change();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
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
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'is_active',
                'is_deleted',
            ]);
            $table->unsignedBigInteger('id')->change();
        });
    }
};
