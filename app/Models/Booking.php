<?php

// filepath: app/Models/Booking.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'service_type', 'tour_package_id', 'car_rental_package_id', 'booking_reference', 'booking_source', 'customer_id',
        'customer_name', 'customer_email', 'customer_phone', 'customer_passport_number',
        'agent_id', 'adults', 'children', 'service_date', 'service_end_date',
        'original_price', 'selling_price', 'additional_charges', 'discount_amount',
        'final_amount', 'paid_amount', 'due_amount', 'payment_status', 'status',
        'special_requirements', 'allow_partial_payment', 'minimum_payment_amount',
        'admin_override_payment', 'payment_notes', 'internal_notes', 'booked_by',
        'agent_discount_percent', 'agent_cost_price', 'booking_details'
    ];

    protected $casts = [
        'service_date' => 'date',
        'service_end_date' => 'date',
        'original_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'agent_discount_percent' => 'decimal:2',
        'agent_cost_price' => 'decimal:2',
        'allow_partial_payment' => 'boolean',
        'admin_override_payment' => 'boolean',
        'adults' => 'integer',
        'children' => 'integer',
        'booking_details' => 'array',
    ];

    protected $appends = [
        'total_passengers',
        'duration_days',
        'payment_progress',
        'remaining_amount'
    ];

    // === RELATIONSHIPS ===
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Tour relationships
    public function tourDetails()
    {
        return $this->hasOne(TourBookingDetail::class);
    }

    public function tourPackage()
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    // Car rental relationships
    public function carRentalDetails()
    {
        return $this->hasOne(CarRentalBookingDetail::class);
    }

    public function carRentalPackage()
    {
        return $this->belongsTo(CarRentalPackage::class, 'car_rental_package_id');
    }

    // === BOOT METHOD ===
    protected static function booted()
    {
        static::creating(function ($booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = self::generateBookingReference($booking->service_type);
            }

            // Calculate final amount
            $booking->final_amount = $booking->calculateFinalAmount();
            $booking->due_amount = $booking->final_amount;
        });

        static::created(function ($booking) {
            // Auto-create tour details if service_type is TOURS
            if ($booking->service_type === 'TOURS' && $booking->tour_package_id) {
                $tourDetails = session('temp_tour_details', []);

                $booking->tourDetails()->create([
                    'tour_package_id' => $booking->tour_package_id,
                    'pickup_location' => $tourDetails['pickup_location'] ?? null,
                    'pickup_time' => $tourDetails['pickup_time'] ?? '08:00',
                    'drop_location' => $tourDetails['drop_location'] ?? null,
                    'room_type' => $tourDetails['room_type'] ?? 'twin',
                    'meal_plan' => $tourDetails['meal_plan'] ?? 'breakfast',
                    'guide_language' => $tourDetails['guide_language'] ?? 'English',
                    'emergency_contact' => $tourDetails['emergency_contact'] ?? null,
                    'tour_notes' => $tourDetails['tour_notes'] ?? null,
                ]);

                session()->forget('temp_tour_details');
            }


            // Auto-create car rental details if service_type is CAR_RENTAL
            if ($booking->service_type === 'CAR_RENTAL' && $booking->car_rental_package_id) {
                $carDetails = session('temp_car_details', []);

                $booking->carRentalDetails()->create([
                    'car_rental_package_id' => $booking->car_rental_package_id, // ADD THIS LINE
                    'pickup_date' => $carDetails['pickup_date'] ?? $booking->service_date,
                    'return_date' => $carDetails['return_date'] ?? $booking->service_end_date,
                ]);

                session()->forget('temp_car_details');
            }

        });

        static::updating(function ($booking) {
            // Recalculate amounts when updating
            if ($booking->isDirty(['selling_price', 'additional_charges', 'discount_amount'])) {
                $booking->final_amount = $booking->calculateFinalAmount();
                $booking->due_amount = max(0, $booking->final_amount - $booking->paid_amount);
            }
        });
    }

    // === ATTRIBUTES ===
    public function getTotalPassengersAttribute()
    {
        return $this->adults + $this->children;
    }

    public function getDurationDaysAttribute()
    {
        if (!$this->service_date || !$this->service_end_date) {
            return null;
        }
        return $this->service_date->diffInDays($this->service_end_date) + 1;
    }

    public function getPaymentProgressAttribute()
    {
        if ($this->final_amount <= 0) {
            return 0;
        }
        return round(($this->paid_amount / $this->final_amount) * 100, 2);
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->final_amount - $this->paid_amount);
    }

    // === METHODS ===
    public function calculateFinalAmount()
    {
        return max(0, $this->selling_price + $this->additional_charges - $this->discount_amount);
    }

    public static function generateBookingReference($serviceType = 'TOURS')
    {
        $prefix = match($serviceType) {
            'TOURS' => 'ST',
            'CAR_RENTAL' => 'CR',
            'FLIGHT' => 'FL',
            'HOTEL' => 'HT',
            'TRANSFER' => 'TR',
            'CRUISE' => 'CS',
            'TRANSPORT' => 'TP',
            'VISA' => 'VS',
            default => 'BK'
        };

        $year = date('y');
        $month = date('m');

        $count = self::where('service_type', $serviceType)
                    ->whereYear('created_at', date('Y'))
                    ->whereMonth('created_at', date('m'))
                    ->count() + 1;

        return $prefix . $year . $month . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function canMakePayment()
    {
        return $this->status !== 'cancelled' &&
               $this->payment_status !== 'paid' &&
               $this->due_amount > 0;
    }

    public function updatePaymentStatus()
    {
        $totalPaid = $this->payments()->where('status', 'completed')->sum('amount');
        $this->paid_amount = $totalPaid;
        $this->due_amount = max(0, $this->final_amount - $totalPaid);

        if ($totalPaid >= $this->final_amount && $this->final_amount > 0) {
            $this->payment_status = 'paid';
        } elseif ($totalPaid > 0) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'pending';
        }

        $this->save();
    }

    public function getMinimumPaymentRequired()
    {
        if (!$this->allow_partial_payment) {
            return $this->due_amount;
        }
        return $this->minimum_payment_amount ?: 0;
    }

    public function getServiceDetails()
    {
        return match($this->service_type) {
            'TOURS' => $this->tourDetails?->getTourSummary() ?? 'Tour details not available',
            'CAR_RENTAL' => $this->carRentalDetails?->getRentalSummary() ?? 'Car rental details not available',
            default => 'Service details not available'
        };
    }

    public function getServicePackage()
    {
        return match($this->service_type) {
            'TOURS' => $this->tourPackage,
            'CAR_RENTAL' => $this->carRentalPackage,
            default => null
        };
    }

    public function getServiceTitle()
    {
        $package = $this->getServicePackage();
        return $package?->title ?? 'Service package not found';
    }

    // === SCOPES ===
    public function scopeByServiceType($query, $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('service_date', [$startDate, $endDate]);
    }

    public function scopeWithPendingPayments($query)
    {
        return $query->whereIn('payment_status', ['pending', 'partial'])
                    ->where('due_amount', '>', 0);
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeTours($query)
    {
        return $query->where('service_type', 'TOURS');
    }

    public function scopeCarRentals($query)
    {
        return $query->where('service_type', 'CAR_RENTAL');
    }
}
