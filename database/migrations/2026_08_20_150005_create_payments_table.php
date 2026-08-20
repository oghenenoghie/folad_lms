<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Append-only. amount is signed: a normal payment is positive, a reversal
// (self-referencing via reversal_of_payment_id) is the same amount negated.
// There is deliberately no update/delete route for this table anywhere in
// the API -- correcting a mistaken payment means recording a compensating
// reversal, never editing or deleting the original row. See the school
// skill's finance guardrail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount'); // kobo, signed (negative = reversal)
            $table->enum('method', ['cash', 'bank_transfer', 'card', 'cheque', 'other']);
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('reversal_of_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
