<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'config',
        'is_public',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_public' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledReports(): HasMany
    {
        return $this->hasMany(ScheduledReport::class);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->orWhere('is_public', true);
    }
}
