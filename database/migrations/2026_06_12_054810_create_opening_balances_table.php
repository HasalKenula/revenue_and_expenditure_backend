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
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->integer('head');
            $table->year('year');
            $table->decimal('opening_balance', 15, 2);
            $table->timestamps();

            // Composite unique constraint
            $table->unique(['head', 'year'], 'unique_head_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};
