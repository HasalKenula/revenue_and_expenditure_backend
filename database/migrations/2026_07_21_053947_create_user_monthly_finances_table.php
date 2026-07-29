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
        Schema::create('user_monthly_finances', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('username')->nullable();
            $table->string('subject')->nullable();
            $table->integer('trno')->nullable();
            $table->integer('month')->nullable();
            $table->string('sn')->nullable();
            $table->integer('dr_cr_code')->nullable();
            $table->integer('head')->nullable();
            $table->integer('program')->nullable();
            $table->integer('project')->nullable();
            $table->integer('sub_project')->nullable();
            $table->integer('object')->nullable();
            $table->integer('item')->nullable();
            $table->integer('funding')->nullable();
            $table->string('dr_cr')->nullable();
            $table->decimal('cash_xe', 18, 2)->default(0);
            $table->integer('head_no')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('cash', 18, 2)->default(0);
            $table->decimal('xe', 18, 2)->default(0);
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->integer('approved_by')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_approved');

              // Composite Unique Constraint
            $table->unique([
                'user_id',
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
            ], 'user_monthly_finances_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_monthly_finances');
    }
};
