<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'code',
        'name',
        'total_budget',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_budget' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function subActivities(): HasMany
    {
        return $this->hasMany(SubActivity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
