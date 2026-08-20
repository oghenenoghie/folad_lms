<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A term's fee bill for one class level -- the template invoices are
// generated from. Draft (published_at null) while its fee_structure_items
// are still being set up; publishing locks it and generates an invoice for
// every currently enrolled student in that class level for that term.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['class_level_id', 'term_id']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
