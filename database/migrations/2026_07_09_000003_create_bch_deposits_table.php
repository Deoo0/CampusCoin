<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bch_deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('bch_address');
            $table->decimal('bch_amount', 18, 8)->nullable();
            $table->decimal('php_rate_at_deposit', 18, 2)->nullable();
            $table->decimal('cc_credited', 18, 2)->nullable();
            $table->string('txid')->nullable()->index();
            $table->enum('status', ['PENDING', 'CONFIRMED', 'FAILED','CANCELLED'])->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bch_deposits');
    }
};
