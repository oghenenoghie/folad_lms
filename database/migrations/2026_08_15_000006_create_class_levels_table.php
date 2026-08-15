<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The grade level: "JSS 1" … "SS 3". Holds one or more arms.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');              // "JSS 1"
            $table->unsignedSmallInteger('rank'); // ordering across levels
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_levels');
    }
};
