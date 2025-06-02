<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarRentalBookingLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_rental_package_id', 'date', 'max_booking_override', 'special_daily_price'
    ];

    protected $casts = [
        'date' => 'date',
        'special_daily_price' => 'decimal:2',
    ];

    // === RELATIONSHIPS ===
    public function carRentalPackage()
    {
        return $this->belongsTo(CarRentalPackage::class);
    }

    // === SCOPES ===
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeWithSpecialPrice($query)
    {
        return $query->whereNotNull('special_daily_price');
    }

    public function scopeWithBookingLimit($query)
    {
        return $query->whereNotNull('max_booking_override');
    }
}
