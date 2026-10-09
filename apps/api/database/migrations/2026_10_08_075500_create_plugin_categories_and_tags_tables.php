<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('category_plugin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plugin_id')->constrained()->cascadeOnDelete();
            
            $table->unique(['category_id', 'plugin_id']);
        });

        Schema::create('plugin_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('plugin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            
            $table->unique(['plugin_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_tag');
        Schema::dropIfExists('category_plugin');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
