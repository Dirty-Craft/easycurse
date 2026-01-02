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
        Schema::table('mod_set_items', function (Blueprint $table) {
            $table->unsignedBigInteger('curseforge_mod_id')->nullable()->after('mod_set_id');
            $table->unsignedBigInteger('curseforge_file_id')->nullable()->after('curseforge_mod_id');
            $table->string('curseforge_slug')->nullable()->after('curseforge_file_id');

            $table->index('curseforge_mod_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_set_items', function (Blueprint $table) {
            $table->dropIndex(['curseforge_mod_id']);
            $table->dropColumn(['curseforge_mod_id', 'curseforge_file_id', 'curseforge_slug']);
        });
    }
};
