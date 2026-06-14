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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();

            
            $table->integer('head')->nullable();
            $table->integer('program')->nullable();
            $table->integer('project')->nullable();
            $table->integer('subproj')->nullable();
            $table->integer('object')->nullable();
            $table->string('obj_detail')->nullable();
            $table->integer('funding')->nullable();
            $table->string('objname')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
