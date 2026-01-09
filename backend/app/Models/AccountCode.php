<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'level',
        'parent_code',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Level descriptions
     */
    public const LEVELS = [
        1 => 'Kelompok',
        2 => 'Jenis',
        3 => 'Objek',
        4 => 'Rincian Objek',
        5 => 'Sub Rincian Objek',
    ];

    /**
     * Get the parent account code
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccountCode::class, 'parent_code', 'code');
    }

    /**
     * Get the child account codes
     */
    public function children(): HasMany
    {
        return $this->hasMany(AccountCode::class, 'parent_code', 'code');
    }

    /**
     * Get budget items using this account code
     */
    public function budgetItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class, 'account_code', 'code');
    }

    /**
     * Get level name attribute
     */
    public function getLevelNameAttribute(): string
    {
        return self::LEVELS[$this->level] ?? 'Unknown';
    }

    /**
     * Scope for active codes only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific level
     */
    public function scopeOfLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for leaf nodes (level 5)
     */
    public function scopeLeafNodes($query)
    {
        return $query->where('level', 5);
    }

    /**
     * Get all ancestors (parent chain)
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        
        while ($current) {
            array_unshift($ancestors, $current);
            $current = $current->parent;
        }
        
        return $ancestors;
    }

    /**
     * Get full path (code with ancestors)
     */
    public function getFullPathAttribute(): string
    {
        $ancestors = $this->getAncestors();
        $codes = array_map(fn($a) => $a->code, $ancestors);
        $codes[] = $this->code;
        
        return implode(' > ', $codes);
    }
}
