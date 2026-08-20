<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// audience is the broad bucket (all/staff/students/guardians); class_level_id
// and class_arm_id optionally narrow a 'students' or 'guardians' audience to
// one class (e.g. "JSS 1 field trip") -- at most one of the two should be
// set, enforced in the form request rather than the schema.
// published_at null = draft, visible only to admin-tier staff; a future
// timestamp schedules it; null/past-or-now makes it visible per audience.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('audience', ['all', 'staff', 'students', 'guardians'])->default('all');
            $table->foreignId('class_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('class_arm_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
