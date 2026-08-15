<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;
// use Spatie\Permission\Traits\HasRoles;   // enable after installing spatie/laravel-permission

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes; // add HasRoles here

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function school(): BelongsTo   { return $this->belongsTo(School::class); }
    public function staff(): HasOne       { return $this->hasOne(Staff::class); }
    public function student(): HasOne     { return $this->hasOne(Student::class); }
    public function guardian(): HasOne    { return $this->hasOne(Guardian::class); }

    /** super_admin has a NULL school_id and bypasses the tenant scope. */
    public function isSuperAdmin(): bool
    {
        return is_null($this->school_id);
    }
}
