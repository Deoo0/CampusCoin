<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cc_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignUuid('to_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->enum('type', ['DEPOSIT_CREDIT', 'ORDER_PAYMENT', 'ORDER_RELEASE', 'WITHDRAWAL']);
            $table->uuid('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cc_transactions');
    }
};
