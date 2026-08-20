<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Extends the default Laravel users table. Roles are handled by
// spatie/laravel-permission (teams = school_id), not a column here.
// super_admin users have a NULL school_id and bypass the tenant scope.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')
                  ->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->softDeletes();

            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn(['school_id', 'phone', 'avatar_path', 'is_active', 'deleted_at']);
        });
    }
};
