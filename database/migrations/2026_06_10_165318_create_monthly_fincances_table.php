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
        Schema::create('monthly_fincances', function (Blueprint $table) {
            $table->id();
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
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_fincances');
    }
};
