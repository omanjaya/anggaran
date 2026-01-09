<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_item_id',
        'month',
        'year',
        'start_date',
        'end_date',
        'description',
        'pic_user_id',
        'status',
        'planned_volume',
        'planned_amount',
        'priority',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'planned_volume' => 'decimal:2',
            'planned_amount' => 'decimal:2',
        ];
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function scopeByMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'PLANNED');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'IN_PROGRESS');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'PLANNED' => 'Direncanakan',
            'IN_PROGRESS' => 'Sedang Berjalan',
            'COMPLETED' => 'Selesai',
            'CANCELLED' => 'Dibatalkan',
            default => $this->status,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            1 => 'Rendah',
            2 => 'Sedang',
            3 => 'Tinggi',
            default => 'Rendah',
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
