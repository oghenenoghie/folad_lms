<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A named set of grade bands (A1-F9, A-F, etc). class_level_id null = the
// school-wide default; a scale tied to a specific level overrides it for
// that level (e.g. SS grades differently from JSS). Exactly one is_default
// scale should exist per (school_id, class_level_id) pair — including the
// null/school-wide slot — enforced in application logic, same as
// academic_sessions.is_current.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['school_id', 'class_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scales');
    }
};
