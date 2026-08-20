<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One bill per enrollment per term, generated from a published fee_structure.
// amount_due is a locked snapshot taken at generation time (copied from the
// fee_structure's items, which are copied again onto invoice_items) -- unlike
// current_class/grade/position elsewhere in this API, a bill is a historical
// financial fact and must NOT drift if the fee_structure is edited later, so
// this is deliberately NOT "derive, don't denormalise".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_structure_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_due'); // kobo, snapshot at generation
            $table->date('issued_at');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'term_id']);
            $table->index(['school_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
