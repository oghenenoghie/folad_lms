<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Staff, including teachers. A staff row optionally links to a user account
// (some support staff may not log in). Form teachers reference this table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('staff_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('designation')->nullable(); // "Mathematics Teacher"
            $table->string('department')->nullable();
            $table->date('employment_date')->nullable();
            $table->enum('status', ['active', 'on_leave', 'terminated'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'staff_number']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
