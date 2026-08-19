<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deleting an assessment_component originally cascaded to delete every
// result recorded against it -- meaning an admin fixing a typo'd component
// name via delete-and-recreate (instead of update) would silently wipe
// recorded scores with no warning. Results are academic records; that's the
// wrong default (same reasoning as why enrollments/class_arms are
// restrictOnDelete rather than cascade). Switches it to restrict so the API
// has to make an explicit choice (and can return a clean 409) instead.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['assessment_component_id']);
        });

        Schema::table('results', function (Blueprint $table) {
            $table->foreign('assessment_component_id')
                ->references('id')->on('assessment_components')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['assessment_component_id']);
        });

        Schema::table('results', function (Blueprint $table) {
            $table->foreign('assessment_component_id')
                ->references('id')->on('assessment_components')
                ->cascadeOnDelete();
        });
    }
};
