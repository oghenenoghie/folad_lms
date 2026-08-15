<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The concrete class: "JSS 1A". Display = level.name . ' ' . arm.name.
// restrictOnDelete on class_level so you can't orphan arms.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_arms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_teacher_id')->nullable()
                  ->constrained('staff')->nullOnDelete();
            $table->string('name');              // "A", "Gold", "Diamond"
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['class_level_id', 'name']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_arms');
    }
};
