<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The actual cutoffs within a grading scale, e.g. A1: 75-100 "Excellent".
// Carries its own school_id (like every core table here) even though it's
// always reached through its parent grading_scale, per the "if unsure
// whether a table is tenant-scoped, it is" guardrail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scale_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $table->string('grade');              // "A1", "B2", "F9"
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->string('remark')->nullable(); // "Excellent", "Fail"
            $table->timestamps();

            $table->unique(['grading_scale_id', 'grade']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scale_bands');
    }
};
