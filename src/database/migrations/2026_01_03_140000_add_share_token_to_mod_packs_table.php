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
            $table->string('share_token', 64)->nullable()->unique()->after('software');
            $table->index('share_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_packs', function (Blueprint $table) {
            $table->dropIndex(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
