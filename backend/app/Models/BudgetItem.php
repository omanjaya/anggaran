<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetItem extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'sub_activity_id',
        'code',
        'name',
        'unit',
        'volume',
        'unit_price',
        'total_budget',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_budget' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class);
    }

    public function monthlyPlans(): HasMany
    {
        return $this->hasMany(MonthlyPlan::class);
    }

    public function operationalSchedules(): HasMany
    {
        return $this->hasMany(OperationalSchedule::class);
    }

    public function realizations(): HasMany
    {
        return $this->hasMany(MonthlyRealization::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function calculateTotalBudget(): float
    {
        return $this->volume * $this->unit_price;
    }
}
