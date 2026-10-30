<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('transaction_uuid')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending'); // pending|success|failed
            $table->json('metadata')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('success_url')->nullable();
            $table->string('failure_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};