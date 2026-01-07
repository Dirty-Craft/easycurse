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
        Schema::table('mod_pack_items', function (Blueprint $table) {
            $table->string('modrinth_project_id')->nullable()->after('curseforge_slug');
            $table->string('modrinth_version_id')->nullable()->after('modrinth_project_id');
            $table->string('modrinth_slug')->nullable()->after('modrinth_version_id');
            $table->string('source')->nullable()->after('modrinth_slug')->comment('Platform source: curseforge or modrinth');

            $table->index('modrinth_project_id');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_pack_items', function (Blueprint $table) {
            $table->dropIndex(['modrinth_project_id']);
            $table->dropIndex(['source']);
            $table->dropColumn(['modrinth_project_id', 'modrinth_version_id', 'modrinth_slug', 'source']);
        });
    }
};
