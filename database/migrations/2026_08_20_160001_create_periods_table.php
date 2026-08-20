<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One shared daily schedule for the whole school ("Period 1" 08:00-08:40,
// "Break" 10:00-10:20, ...), reused by every class arm's timetable rather
// than varying per class level -- the simpler default confirmed for this
// build. is_teaching_period=false marks slots (break, assembly, lunch)
// that a timetable_entry should never be created against.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('sequence');
            $table->boolean('is_teaching_period')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
