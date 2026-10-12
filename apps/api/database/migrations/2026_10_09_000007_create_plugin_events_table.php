<?php

use App\Enums\PluginEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plugin_id')->constrained()->cascadeOnDelete();

            // nullOnDelete rather than cascade: the timeline has to outlive the
            // admin account that wrote it. Cascading would erase a plugin's whole
            // review history because one reviewer was deleted.
            $table->foreignUuid('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('event_type', array_column(PluginEventType::cases(), 'value'));

            // Nullable because it is only ever written by an admin: `created`
            // and `resubmitted` are the owner's own actions and carry no text,
            // and an approval needs no explanation.
            $table->text('message')->nullable();

            // No updated_at. An event is append-only — nothing edits or removes
            // one, so a modified column would only ever hold null.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['plugin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_events');
    }
};
