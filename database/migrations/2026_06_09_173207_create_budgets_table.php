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

            // Indexes
            $table->index('head');
            $table->index('program');
            $table->index('project');
            $table->index('subproj');
            $table->index('object');

            // Composite unique constraint
            $table->unique(
                [
                    'head',
                    'program',
                    'project',
                    'subproj',
                    'object'
                ],
                'unique_budget_record'
            );
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
