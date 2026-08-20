<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The line-item breakdown of one invoice -- copied from fee_structure_items
// at generation time, so it stays correct even if the source fee_structure
// changes afterwards (a fee_structure is locked once published anyway, but
// this keeps the invoice self-contained regardless).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount'); // kobo
            $table->timestamps();

            $table->index(['school_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
