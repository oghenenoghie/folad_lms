<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tenant root. Every tenant-scoped table carries school_id and is isolated
// at the application layer (global scope + BelongsToSchool trait) — MySQL has no RLS.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();            // short slug, e.g. "greenfield"
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('motto')->nullable();
            $table->char('currency', 3)->default('NGN'); // ISO 4217; drives minor-unit exponent
            $table->json('settings')->nullable();        // grading scale defaults, term count, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
