<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
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

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
