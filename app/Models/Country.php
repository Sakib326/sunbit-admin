<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'flag',
        'currency',
        'currency_symbol',
        'myr_exchange_rate',
        'usd_exchange_rate',
        'exchange_rate_updated_at',
        'status',
    ];

    protected $casts = [
        'myr_exchange_rate' => 'decimal:4',
        'usd_exchange_rate' => 'decimal:4',
        'exchange_rate_updated_at' => 'datetime',
        'status' => 'string',
    ];

    // Convert amount from local currency to Malaysian Ringgit
    public function convertToMYR(float $amount): float
    {
        return round($amount * $this->myr_exchange_rate, 2);
    }

    // Convert amount from Malaysian Ringgit to local currency
    public function convertFromMYR(float $amount): float
    {
        if ($this->myr_exchange_rate == 0) {
            return $amount;
        }
        return round($amount / $this->myr_exchange_rate, 2);
    }

    // Convert amount from local currency to USD
    public function convertToUSD(float $amount): float
    {
        if (!$this->usd_exchange_rate) {
            return $amount;
        }
        return round($amount * $this->usd_exchange_rate, 2);
    }

    // Format currency with symbol
    public function formatCurrency(float $amount): string
    {
        return ($this->currency_symbol ?? '') . ' ' . number_format($amount, 2);
    }

    // Format MYR currency
    public function formatMYR(float $amount): string
    {
        return 'RM ' . number_format($this->convertToMYR($amount), 2);
    }

    // Check if exchange rate is outdated (older than 24 hours)
    public function isExchangeRateOutdated(): bool
    {
        if (!$this->exchange_rate_updated_at) {
            return true;
        }
        return $this->exchange_rate_updated_at->lt(now()->subHours(24));
    }

    // Scope for active countries
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }
}
