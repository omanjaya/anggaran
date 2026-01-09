<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityIndicator extends Model
{
    protected $fillable = [
        'sub_activity_id',
        'type',
        'tolak_ukur',
        'target',
    ];

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class);
    }
}
