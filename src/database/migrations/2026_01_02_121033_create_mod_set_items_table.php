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
        Schema::create('mod_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_set_id')->constrained()->cascadeOnDelete();
            $table->string('mod_name');
            $table->string('mod_version');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('mod_set_id');
            $table->index(['mod_set_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_set_items');
    }
};
