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
        'nomor_dpa',
        'name',
        'total_budget',
        'sumber_pendanaan',
        'lokasi',
        'keluaran',
        'waktu_pelaksanaan',
        'alokasi_tahun_minus_1',
        'alokasi_tahun_plus_1',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_budget' => 'decimal:2',
            'alokasi_tahun_minus_1' => 'decimal:2',
            'alokasi_tahun_plus_1' => 'decimal:2',
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

    public function indicators(): HasMany
    {
        return $this->hasMany(ActivityIndicator::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
