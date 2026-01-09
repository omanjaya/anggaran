<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monthly_realization_id',
        'from_status',
        'to_status',
        'action',
        'notes',
        'performed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ApprovalStatus::class,
            'to_status' => ApprovalStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function monthlyRealization(): BelongsTo
    {
        return $this->belongsTo(MonthlyRealization::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
