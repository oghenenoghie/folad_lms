<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Daily attendance (one row per student per day), not per-period -- matches
// how Nigerian schools actually run the morning register rather than a
// period-by-period US-style model. One row per enrollment per date.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->foreignId('recorded_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['enrollment_id', 'date']);
            $table->index(['school_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
