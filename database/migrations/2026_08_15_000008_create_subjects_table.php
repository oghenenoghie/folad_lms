<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');              // "Mathematics"
            $table->string('code')->nullable();  // "MTH"
            $table->boolean('is_core')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
