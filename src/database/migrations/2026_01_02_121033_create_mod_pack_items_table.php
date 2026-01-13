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
        Schema::create('mod_pack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_pack_id')->constrained()->cascadeOnDelete();
            $table->string('mod_name');
            $table->string('mod_version');
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('curseforge_mod_id')->nullable();
            $table->unsignedBigInteger('curseforge_file_id')->nullable();
            $table->string('curseforge_slug')->nullable();
            $table->boolean('is_auto_added')->default(false);
            $table->timestamp('last_update_notified_at')->nullable();
            $table->string('modrinth_project_id')->nullable();
            $table->string('modrinth_version_id')->nullable();
            $table->string('modrinth_slug')->nullable();
            $table->string('source')->nullable()->comment('Platform source: curseforge or modrinth');
            $table->timestamps();

            $table->index('mod_pack_id');
            $table->index(['mod_pack_id', 'sort_order']);
            $table->index('curseforge_mod_id');
            $table->index('modrinth_project_id');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_pack_items');
    }
};
