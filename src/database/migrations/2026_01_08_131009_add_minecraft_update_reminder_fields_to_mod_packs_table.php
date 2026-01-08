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
        Schema::table('mod_packs', function (Blueprint $table) {
            $table->string('minecraft_update_reminder_version')->nullable()->after('downloads_count');
            $table->string('minecraft_update_reminder_software')->nullable()->after('minecraft_update_reminder_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_packs', function (Blueprint $table) {
            $table->dropColumn(['minecraft_update_reminder_version', 'minecraft_update_reminder_software']);
        });
    }
};
