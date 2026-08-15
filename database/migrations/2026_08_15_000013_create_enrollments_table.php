<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The key link: a student sits in one class_arm for one academic_session.
// This is the single source of truth for "what class is this student in".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'promoted', 'repeated', 'transferred', 'withdrawn'])
                  ->default('active');
            $table->timestamps();

            // one active placement per student per session
            $table->unique(['student_id', 'academic_session_id']);
            $table->index(['school_id', 'class_arm_id']);
            $table->index(['academic_session_id', 'class_arm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
