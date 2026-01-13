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
        Schema::create('mod_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('minecraft_version');
            $table->text('description')->nullable();
            $table->string('software')->default('forge');
            $table->string('minecraft_update_reminder_version')->nullable();
            $table->string('minecraft_update_reminder_software')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index('share_token');
            $table->index('user_id');
            $table->index('minecraft_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_packs');
    }
};
