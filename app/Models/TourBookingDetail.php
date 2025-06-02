<?php

// filepath: app/Models/TourBookingDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TourBookingDetail extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'booking_id', 'tour_package_id', 'pickup_location', 'pickup_time',
        'drop_location', 'room_type', 'meal_plan', 'guide_language',
        'emergency_contact', 'tour_notes'
    ];

    protected $casts = [
        'pickup_time' => 'datetime:H:i',
    ];

    protected $appends = [
        'room_type_label',
        'meal_plan_label'
    ];

    // === RELATIONSHIPS ===
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Uncomment when tour_packages table is created
    // public function tourPackage()
    // {
    //     return $this->belongsTo(TourPackage::class);
    // }

    // === ATTRIBUTES ===
    public function getRoomTypeLabelAttribute()
    {
        return match($this->room_type) {
            'single' => 'Single Room',
            'twin' => 'Twin Sharing',
            'triple' => 'Triple Sharing',
            'family' => 'Family Room',
            default => ucfirst($this->room_type)
        };
    }

    public function getMealPlanLabelAttribute()
    {
        return match($this->meal_plan) {
            'no_meals' => 'No Meals',
            'breakfast' => 'Breakfast Only',
            'half_board' => 'Half Board (Breakfast + Dinner)',
            'full_board' => 'Full Board (All Meals)',
            default => ucfirst($this->meal_plan)
        };
    }

    // === HELPER METHODS ===
    public function getFormattedPickupTimeAttribute()
    {
        return $this->pickup_time ? $this->pickup_time->format('g:i A') : null;
    }

    public function getTourSummary()
    {
        $summary = [];

        if ($this->pickup_location) {
            $summary[] = "Pickup: {$this->pickup_location}";
        }

        if ($this->pickup_time) {
            $summary[] = "Time: {$this->formatted_pickup_time}";
        }

        $summary[] = "Room: {$this->room_type_label}";
        $summary[] = "Meals: {$this->meal_plan_label}";

        if ($this->guide_language !== 'English') {
            $summary[] = "Guide: {$this->guide_language}";
        }

        return implode(' | ', $summary);
    }
}
