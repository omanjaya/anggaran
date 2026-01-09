<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyRealization extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'monthly_plan_id',
        'realized_volume',
        'realized_amount',
        'deviation_volume',
        'deviation_amount',
        'deviation_percentage',
        'status',
        'submitted_by',
        'submitted_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'locked_at',
        'locked_by',
        'notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'realized_volume' => 'decimal:2',
            'realized_amount' => 'decimal:2',
            'deviation_volume' => 'decimal:2',
            'deviation_amount' => 'decimal:2',
            'deviation_percentage' => 'decimal:2',
            'status' => ApprovalStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function monthlyPlan(): BelongsTo
    {
        return $this->belongsTo(MonthlyPlan::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RealizationDocument::class);
    }

    public function approvalHistories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class);
    }

    public function calculateDeviation(): void
    {
        $plan = $this->monthlyPlan;

        $this->deviation_volume = $this->realized_volume - $plan->planned_volume;
        $this->deviation_amount = $this->realized_amount - $plan->planned_amount;
        $this->deviation_percentage = $plan->planned_amount > 0
            ? (($this->realized_amount - $plan->planned_amount) / $plan->planned_amount) * 100
            : 0;
    }

    public function scopeByStatus($query, ApprovalStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [ApprovalStatus::SUBMITTED, ApprovalStatus::VERIFIED]);
    }
}
