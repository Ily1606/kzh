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
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE TEMP TABLE user_id_map (old_id varchar(36) PRIMARY KEY, new_id uuid NOT NULL)');

            foreach (DB::table('users')->pluck('id') as $userId) {
                DB::table('user_id_map')->insert([
                    'old_id' => (string) $userId,
                    'new_id' => Str::isUuid((string) $userId)
                        ? (string) $userId
                        : (string) Str::uuid(),
                ]);
            }

            DB::statement('ALTER TABLE users ALTER COLUMN id TYPE varchar(36) USING id::varchar');
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE varchar(36) USING user_id::varchar');
            DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE varchar(36) USING tokenable_id::varchar');

            DB::statement("UPDATE sessions s
                SET user_id = m.new_id::varchar
                FROM user_id_map m
                WHERE s.user_id = m.old_id");

            DB::statement("UPDATE personal_access_tokens p
                SET tokenable_id = m.new_id::varchar
                FROM user_id_map m
                WHERE p.tokenable_type = 'App\\Models\\User'
                  AND p.tokenable_id = m.old_id");

            DB::statement("UPDATE users u
                SET id = m.new_id::varchar
                FROM user_id_map m
                WHERE u.id = m.old_id");

            DB::statement('ALTER TABLE users ALTER COLUMN id TYPE uuid USING id::uuid');
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE uuid USING NULLIF(user_id, \'\')::uuid');

            DB::statement('DROP TABLE user_id_map');
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->uuid('id')->change();
            });

            Schema::table('sessions', function (Blueprint $table): void {
                $table->uuid('user_id')->nullable()->change();
            });

            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->string('tokenable_id', 36)->change();
            });
        }

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE varchar(36) USING user_id::varchar');
            DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE bigint USING tokenable_id::bigint');
            DB::statement('ALTER TABLE users ALTER COLUMN id TYPE bigint USING id::bigint');
        } else {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->string('user_id', 36)->nullable()->change();
            });

            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->unsignedBigInteger('tokenable_id')->change();
            });

            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('id')->change();
            });
        }

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
