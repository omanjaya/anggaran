<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealizationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'monthly_realization_id',
        'filename',
        'original_filename',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function monthlyRealization(): BelongsTo
    {
        return $this->belongsTo(MonthlyRealization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
