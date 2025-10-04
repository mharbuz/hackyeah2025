<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PensionSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'pension_value',
        'calculation_data',
        'expires_at',
    ];

    protected $casts = [
        'pension_value' => 'decimal:2',
        'calculation_data' => 'array',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
            
            // Set expiration to 30 days from now if not set
            if (empty($model->expires_at)) {
                $model->expires_at = Carbon::now()->addDays(30);
            }
        });
    }

    /**
     * Check if the session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope to get only non-expired sessions
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}

