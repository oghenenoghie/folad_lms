<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The raw entered marks -- one row per student per subject per assessment
// component ("Ada's 1st CA score in Mathematics"). Points at enrollment_id
// rather than student_id + academic_session_id separately: an enrollment
// already pins a student to one class_arm for one session, and every term
// within that session reuses the same enrollment_id, so it doubles as the
// class_arm needed to compute a student's position among their peers.
//
// Term totals, grade letters, and class position are all DERIVED from these
// rows at read time (see the school skill's "derive, don't denormalise"
// convention) -- nothing here stores a computed aggregate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->foreignId('recorded_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['enrollment_id', 'subject_id', 'assessment_component_id']);
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
