<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id',10)->unique();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('enrollment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->decimal('amount', 10, 2);

            $table->string('currency', 10)->default('USD');

            $table->enum('payment_method', ['paypal', 'stripe', 'token']);

            $table->string('transaction_id')->nullable()->unique();

            $table->enum('status', ['pending', 'paid', 'failed'])
                ->default('pending');

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
