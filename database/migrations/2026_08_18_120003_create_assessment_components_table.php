<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What a term's scoring is made of ("1st CA: 20", "2nd CA: 20", "Exam: 60"),
// applied uniformly across every subject in that term -- schools rarely vary
// the CA/exam split per subject, but do sometimes vary it term to term
// (e.g. an exam-only third term), hence scoped to term_id rather than being
// a permanent school-wide template.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('name');               // "1st CA", "Exam"
            $table->decimal('max_score', 5, 2);
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['term_id', 'name']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_components');
    }
};
