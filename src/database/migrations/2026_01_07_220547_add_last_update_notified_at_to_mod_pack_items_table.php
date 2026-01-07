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
            $table->timestamp('last_update_notified_at')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_pack_items', function (Blueprint $table) {
            $table->dropColumn('last_update_notified_at');
        });
    }
};
