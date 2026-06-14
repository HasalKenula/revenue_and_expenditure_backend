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
        Schema::create('impress_issues', function (Blueprint $table) {
            $table->id();
            $table->integer('head');
            $table->year('year');
            $table->integer('month'); // 1-12
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            // Add indexes for better performance
            $table->index('head');
            $table->index('year');
            $table->index('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impress_issues');
    }
};
