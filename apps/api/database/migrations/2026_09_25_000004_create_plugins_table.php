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
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name']);
            $table->index('status');
            $table->index(['user_id', 'created_at']);
        });

        // Trigram search over the plugin name and title.
        //
        // Disabled: `CREATE EXTENSION` needs a privilege the app's DB role does
        // not have on a managed Postgres, so this fails at migrate time. The
        // extension belongs to the infrastructure layer — create it in the local
        // Postgres init script, and have the DBA run it once per staging/prod
        // database. Until then the catalogue search scans the whole table.
        //
        //
        // if (DB::getDriverName() !== 'pgsql') {
        //     return;
        // }
        //
        // DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        // DB::statement('CREATE INDEX plugins_name_trgm_idx ON plugins USING GIN (name gin_trgm_ops)');
        // DB::statement('CREATE INDEX plugins_title_trgm_idx ON plugins USING GIN (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
