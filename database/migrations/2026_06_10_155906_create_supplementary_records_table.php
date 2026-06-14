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
        Schema::create('supplementary_records', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->integer('year')->nullable();
            $table->integer('month')->nullable();
            $table->integer('head')->nullable();
            $table->integer('program')->nullable();
            $table->integer('project')->nullable();
            $table->integer('subproject')->nullable();
            $table->integer('object')->nullable();
            $table->integer('subobject')->nullable();
            $table->decimal('fr66p', 15, 2)->default(0);
            $table->decimal('fr66m', 15, 2)->default(0);
            $table->decimal('supplementary_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index('order_number');
            $table->index('year');
            $table->index('month');
            $table->index('head');
            $table->index('program');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplementary_records');
    }
};
