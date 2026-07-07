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
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->integer('head')->nullable();
            $table->integer('program')->nullable();
            $table->integer('project')->nullable();
            $table->integer('sub_project')->nullable();
            $table->integer('object')->nullable();
            $table->string('revenue_code_name')->nullable();
            $table->decimal('estimate', 15, 2)->nullable();
            $table->decimal('re_estimate', 15, 2)->nullable();
            $table->timestamps();

            // Add indexes for better performance
            $table->index('head');
            $table->index('program');
            $table->index('project');
            $table->index('object');
            $table->index('revenue_code_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
