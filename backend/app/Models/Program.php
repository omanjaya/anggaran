<?php

namespace App\Models;

use App\Enums\BudgetCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'urusan_pemerintahan_code',
        'urusan_pemerintahan_name',
        'bidang_urusan_code',
        'bidang_urusan_name',
        'name',
        'category',
        'fiscal_year',
        'total_budget',
        'is_active',
        'skpd_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => BudgetCategory::class,
            'fiscal_year' => 'integer',
            'total_budget' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, BudgetCategory $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByFiscalYear($query, int $year)
    {
        return $query->where('fiscal_year', $year);
    }
}
