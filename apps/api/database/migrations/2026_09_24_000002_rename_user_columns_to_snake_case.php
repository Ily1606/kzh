<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'isActive' => 'is_active',
            'avatarLink' => 'avatar_link',
            'githubName' => 'github_name',
            'githubLink' => 'github_link',
            'isDeleted' => 'is_deleted',
        ];

        foreach ($renames as $oldName => $newName) {
            if (Schema::hasColumn('users', $oldName) && ! Schema::hasColumn('users', $newName)) {
                Schema::table('users', function (Blueprint $table) use ($oldName, $newName): void {
                    $table->renameColumn($oldName, $newName);
                });
            }
        }
    }

    public function down(): void
    {
        $renames = [
            'is_active' => 'isActive',
            'avatar_link' => 'avatarLink',
            'github_name' => 'githubName',
            'github_link' => 'githubLink',
            'is_deleted' => 'isDeleted',
        ];

        foreach ($renames as $oldName => $newName) {
            if (Schema::hasColumn('users', $oldName) && ! Schema::hasColumn('users', $newName)) {
                Schema::table('users', function (Blueprint $table) use ($oldName, $newName): void {
                    $table->renameColumn($oldName, $newName);
                });
            }
        }
    }
};
