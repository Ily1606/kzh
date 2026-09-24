<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('avatar_link')->nullable();
            $table->string('github_name')->nullable();
            $table->string('github_link')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasColumn('users', 'avatar_link')) {
            return;
        }

        DB::table('users')
            ->select('id', 'avatar_link', 'github_name', 'github_link')
            ->where(function ($query): void {
                $query->whereNotNull('avatar_link')
                    ->orWhereNotNull('github_name')
                    ->orWhereNotNull('github_link');
            })
            ->orderBy('id')
            ->each(function ($user): void {
                DB::table('user_profiles')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'avatar_link' => $user->avatar_link,
                    'github_name' => $user->github_name,
                    'github_link' => $user->github_link,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_link', 'github_name', 'github_link']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_link')->nullable();
            $table->string('github_name')->nullable();
            $table->string('github_link')->nullable();
        });

        DB::table('user_profiles')
            ->select('user_id', 'avatar_link', 'github_name', 'github_link')
            ->orderBy('user_id')
            ->each(function ($profile): void {
                DB::table('users')
                    ->where('id', $profile->user_id)
                    ->update([
                        'avatar_link' => $profile->avatar_link,
                        'github_name' => $profile->github_name,
                        'github_link' => $profile->github_link,
                    ]);
            });

        Schema::dropIfExists('user_profiles');
    }
};
