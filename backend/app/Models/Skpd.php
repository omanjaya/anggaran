<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skpd extends Model
{
    use HasFactory;

    protected $table = 'skpd';

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'address',
        'phone',
        'email',
        'head_name',
        'head_title',
        'nip_head',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTotalBudgetAttribute(): float
    {
        return $this->programs()
            ->with('activities.subActivities')
            ->get()
            ->sum(fn($program) => $program->activities->sum(fn($activity) => $activity->subActivities->sum('budget')));
    }
}
