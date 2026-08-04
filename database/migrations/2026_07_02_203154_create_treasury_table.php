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
        Schema::create('treasury', function (Blueprint $table) {
            $table->id();
            $table->string('subject')->nullable()->default('S');
            $table->integer('trno')->nullable()->default(400);
            $table->integer('month')->nullable();
            $table->string('sn')->nullable();
            $table->integer('dr_cr_code')->nullable();
            $table->integer('head')->nullable();
            $table->integer('program')->nullable();
            $table->integer('project')->nullable();
            $table->integer('sub_project')->nullable();
            $table->integer('object')->nullable();
            $table->integer('item')->nullable()->default(0);
            $table->integer('funding')->nullable()->default(11);
            $table->string('dr_cr')->nullable();
            $table->decimal('cash_xe', 18, 2)->default(0);
            $table->integer('head_no')->nullable()->default(400);
            $table->integer('year')->nullable()->default(26);
            $table->decimal('cash', 18, 2)->default(0);
            $table->decimal('xe', 18, 2)->default(0);
            $table->timestamps();


            // Add indexes for better performance
            $table->index('trno');
            $table->index('month');
            $table->index('year');
            $table->index('head');
            $table->index('program');
            $table->index('project');
            $table->index('sub_project');
            $table->index('object');

            // Composite unique constraint
            $table->unique(
                [
                    'trno',
                    'month',
                    'dr_cr_code',
                    'head',
                    'program',
                    'project',
                    'sub_project',
                    'object',
                    'dr_cr',
                    'year'
                ],
                'unique_treasury_record'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury');
    }
};
