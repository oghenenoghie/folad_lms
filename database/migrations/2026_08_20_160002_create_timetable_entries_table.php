<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which subject (and teacher) a class arm has in a given period on a given
// day, for a given term -- scoped to term_id since subject/teacher
// assignments are commonly revised each term, same reasoning as
// assessment_components.
//
// Two composite unique constraints double as the school's two hard
// scheduling rules, enforced at the DB level rather than by hand-rolled
// pre-checks:
//   - a class arm can't be in two places in the same period (class_arm_id
//     + term_id + day_of_week + period_id)
//   - a teacher can't be in two places in the same period (school_id +
//     term_id + day_of_week + period_id + staff_id) -- staff_id is
//     nullable (a slot can be scheduled before a teacher is assigned),
//     and NULLs don't collide against a unique index, so this only bites
//     once a teacher is actually assigned.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->constrained()->restrictOnDelete();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_arm_id', 'term_id', 'day_of_week', 'period_id'], 'timetable_class_arm_slot_unique');
            $table->unique(['school_id', 'term_id', 'day_of_week', 'period_id', 'staff_id'], 'timetable_teacher_slot_unique');
            $table->index(['school_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
