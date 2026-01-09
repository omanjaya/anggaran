<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MonthlyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_item_id',
        'month',
        'year',
        'planned_volume',
        'planned_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'planned_volume' => 'decimal:2',
            'planned_amount' => 'decimal:2',
        ];
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function realization(): HasOne
    {
        return $this->hasOne(MonthlyRealization::class);
    }

    public function scopeByMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
