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
        Schema::create('revenue_opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_number_id')->constrained('account_numbers')->onDelete('cascade');
            $table->decimal('amount', 18, 2)->default(0);
            $table->year('year')->nullable();
            $table->timestamps();
            
            // Add foreign key to estimates table
            $table->foreignId('estimate_id')->nullable()->constrained('estimates')->onDelete('set null');
            
            $table->index('account_number_id');         
            $table->index('estimate_id');
            $table->index('year');
            $table->unique(['account_number_id', 'estimate_id', 'year'], 'unique_opening_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_opening_balances');
    }
};
