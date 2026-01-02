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
            $table->timestamps();

            $table->index('mod_pack_id');
            $table->index(['mod_pack_id', 'sort_order']);
            $table->index('curseforge_mod_id');
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
