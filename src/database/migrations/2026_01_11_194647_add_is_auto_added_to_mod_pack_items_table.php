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
            $table->boolean('is_auto_added')->default(false)->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_pack_items', function (Blueprint $table) {
            $table->dropColumn('is_auto_added');
        });
    }
};
