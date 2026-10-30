<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway')->unique(); // esewa | khalti | nepal_payment | fonepay
            $table->boolean('is_enabled')->default(false);
            $table->string('mode')->default('dev'); // dev | live
            $table->text('dev_credentials')->nullable();  // encrypted JSON
            $table->text('live_credentials')->nullable(); // encrypted JSON
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_settings');
    }
};