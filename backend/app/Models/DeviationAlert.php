<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviationAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_realization_id',
        'budget_item_id',
        'month',
        'year',
        'alert_type',
        'severity',
        'planned_amount',
        'realized_amount',
        'deviation_percentage',
        'message',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:2',
            'realized_amount' => 'decimal:2',
            'deviation_percentage' => 'decimal:2',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function realization(): BelongsTo
    {
        return $this->belongsTo(MonthlyRealization::class, 'monthly_realization_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'CRITICAL');
    }

    public function scopeHigh($query)
    {
        return $query->where('severity', 'HIGH');
    }

    public function getAlertTypeLabelAttribute(): string
    {
        return match($this->alert_type) {
            'UNDER_REALIZATION' => 'Realisasi Kurang',
            'OVER_REALIZATION' => 'Realisasi Melebihi Anggaran',
            'DEADLINE_APPROACHING' => 'Mendekati Deadline',
            'DEADLINE_PASSED' => 'Melewati Deadline',
            'NOT_REALIZED' => 'Belum Direalisasi',
            default => $this->alert_type,
        };
    }

    public function getSeverityLabelAttribute(): string
    {
        return match($this->severity) {
            'LOW' => 'Rendah',
            'MEDIUM' => 'Sedang',
            'HIGH' => 'Tinggi',
            'CRITICAL' => 'Kritis',
            default => $this->severity,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'ACTIVE' => 'Aktif',
            'ACKNOWLEDGED' => 'Diketahui',
            'RESOLVED' => 'Diselesaikan',
            'DISMISSED' => 'Diabaikan',
            default => $this->status,
        };
    }

    public function getMonthNameAttribute(): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$this->month] ?? '';
    }
}
