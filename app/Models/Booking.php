<?php

// filepath: app/Models/Booking.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'service_type', 'booking_reference', 'customer_id', 'agent_id', 'booked_by',
        'customer_email', 'adults', 'children', 'original_price', 'selling_price',
        'agent_discount_percent', 'agent_cost_price', 'additional_charges',
        'discount_amount', 'final_amount', 'paid_amount', 'due_amount',
        'allow_partial_payment', 'minimum_payment_amount', 'customer_name',
        'customer_phone', 'customer_passport_number', 'special_requirements',
        'service_date', 'service_end_date', 'booking_source', 'status',
        'payment_status', 'admin_override_payment', 'payment_notes',
        'internal_notes', 'booking_details'
    ];

    protected $casts = [
        'service_date' => 'date',
        'service_end_date' => 'date',
        'booking_details' => 'array',
        'original_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'agent_discount_percent' => 'decimal:2',
        'agent_cost_price' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'minimum_payment_amount' => 'decimal:2',
        'allow_partial_payment' => 'boolean',
        'admin_override_payment' => 'boolean',
    ];

    protected $appends = [
        'total_passengers',
        'remaining_amount',
        'duration_days',
        'service_status',
        'payment_progress',
        'formatted_booking_reference'
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
        return $this->hasMany(Payment::class)->orderBy('created_at', 'desc');
    }

    public function successfulPayments()
    {
        return $this->hasMany(Payment::class)->where('status', 'completed');
    }

    // Tour relationship - auto-loaded based on service_type
    public function tourDetails()
    {
        return $this->hasOne(TourBookingDetail::class);
    }

    // Dynamic service details based on service_type
    public function getServiceDetailsAttribute()
    {
        return match($this->service_type) {
            'TOURS' => $this->tourDetails,
            'CAR_RENTAL' => $this->carRentalDetails ?? null,
            'FLIGHT' => $this->flightDetails ?? null,
            'HOTEL' => $this->hotelDetails ?? null,
            default => null,
        };
    }

    // === ATTRIBUTES ===
    public function getTotalPassengersAttribute()
    {
        return $this->adults + $this->children;
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->final_amount - $this->paid_amount);
    }

    public function getDurationDaysAttribute()
    {
        if (!$this->service_date || !$this->service_end_date) {
            return 1;
        }
        return $this->service_date->diffInDays($this->service_end_date) + 1;
    }

    public function getServiceStatusAttribute()
    {
        if ($this->status === 'cancelled') {
            return 'Cancelled';
        }
        if ($this->status === 'completed') {
            return 'Completed';
        }

        $today = Carbon::today();
        if ($this->service_date && $this->service_date->isToday()) {
            return 'Today';
        }
        if ($this->service_date && $this->service_date->isFuture()) {
            return 'Upcoming';
        }
        if ($this->service_date && $this->service_date->isPast()) {
            return 'Past Due';
        }

        return 'Active';
    }

    public function getPaymentProgressAttribute()
    {
        if ($this->final_amount <= 0) {
            return 100;
        }
        return round(($this->paid_amount / $this->final_amount) * 100, 2);
    }

    public function getFormattedBookingReferenceAttribute()
    {
        return strtoupper($this->booking_reference);
    }

    // === SCOPES ===
    public function scopeTours(Builder $query)
    {
        return $query->where('service_type', 'TOURS');
    }

    public function scopeCarRentals(Builder $query)
    {
        return $query->where('service_type', 'CAR_RENTAL');
    }

    public function scopeActive(Builder $query)
    {
        return $query->whereIn('status', ['confirmed', 'active']);
    }

    public function scopePending(Builder $query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeUpcoming(Builder $query)
    {
        return $query->where('service_date', '>', Carbon::today());
    }

    public function scopeToday(Builder $query)
    {
        return $query->whereDate('service_date', Carbon::today());
    }

    public function scopeOverdue(Builder $query)
    {
        return $query->where('service_date', '<', Carbon::today())
                    ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeUnpaid(Builder $query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }

    public function scopePartiallyPaid(Builder $query)
    {
        return $query->where('payment_status', 'partial');
    }

    // === BUSINESS LOGIC METHODS ===
    public function calculateFinalAmount()
    {
        $amount = $this->selling_price + $this->additional_charges - $this->discount_amount;
        return max(0, $amount);
    }

    public function updatePaymentStatus()
    {
        $this->final_amount = $this->calculateFinalAmount();

        if ($this->paid_amount >= $this->final_amount) {
            $this->payment_status = 'paid';
            $this->due_amount = 0;
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = 'partial';
            $this->due_amount = $this->final_amount - $this->paid_amount;
        } else {
            $this->payment_status = 'pending';
            $this->due_amount = $this->final_amount;
        }

        $this->save();
    }

    public function canMakePayment()
    {
        return $this->status !== 'cancelled' &&
               $this->payment_status !== 'paid' &&
               $this->remaining_amount > 0;
    }

    public function canCancel()
    {
        return $this->status !== 'cancelled' &&
               $this->status !== 'completed' &&
               (!$this->service_date || $this->service_date->isFuture());
    }

    public function canModify()
    {
        return $this->status !== 'cancelled' &&
               $this->status !== 'completed' &&
               (!$this->service_date || $this->service_date->isAfter(Carbon::today()->addDay()));
    }

    public function isRefundable()
    {
        return $this->paid_amount > 0 &&
               $this->status === 'cancelled' &&
               $this->payment_status !== 'refunded';
    }

    // === AUTO BOOKING REFERENCE GENERATION ===
    public static function generateBookingReference($serviceType)
    {
        $prefix = match($serviceType) {
            'TOURS' => 'TR',
            'CAR_RENTAL' => 'CR',
            'FLIGHT' => 'FL',
            'HOTEL' => 'HT',
            'TRANSFER' => 'TF',
            'CRUISE' => 'CS',
            'TRANSPORT' => 'TP',
            'VISA' => 'VS',
            default => 'BK'
        };

        $year = date('Y');
        $month = date('m');
        $count = self::where('service_type', $serviceType)
                     ->whereYear('created_at', $year)
                     ->whereMonth('created_at', $month)
                     ->count() + 1;

        return "{$prefix}-{$year}{$month}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // === EVENT HANDLING ===
    protected static function booted()
    {
        static::creating(function ($booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = self::generateBookingReference($booking->service_type);
            }

            $booking->final_amount = $booking->calculateFinalAmount();
            $booking->due_amount = $booking->final_amount;
        });

        static::updating(function ($booking) {
            if ($booking->isDirty(['selling_price', 'additional_charges', 'discount_amount'])) {
                $booking->final_amount = $booking->calculateFinalAmount();
                $booking->due_amount = max(0, $booking->final_amount - $booking->paid_amount);
            }
        });

        static::created(function ($booking) {
            // Auto-create tour details if service_type is TOURS
            if ($booking->service_type === 'TOURS') {
                $booking->tourDetails()->create([
                    'tour_package_id' => null, // Will be set later
                    'pickup_time' => '08:00',
                    'room_type' => 'twin',
                    'meal_plan' => 'breakfast',
                    'guide_language' => 'English'
                ]);
            }
        });
    }

    // === HELPER METHODS ===
    public function getServiceTypeColorAttribute()
    {
        return match($this->service_type) {
            'TOURS' => 'success',
            'CAR_RENTAL' => 'warning',
            'FLIGHT' => 'info',
            'HOTEL' => 'primary',
            'TRANSFER' => 'secondary',
            default => 'gray'
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'confirmed' => 'success',
            'active' => 'info',
            'completed' => 'primary',
            'cancelled' => 'danger',
            'draft' => 'warning',
            default => 'gray'
        };
    }

    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'paid' => 'success',
            'partial' => 'warning',
            'pending' => 'danger',
            'refunded' => 'info',
            'zero_payment' => 'gray',
            default => 'gray'
        };
    }

    // === STATISTICS METHODS ===
    public static function getBookingStats($period = 'month')
    {
        $query = self::query();

        if ($period === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($period === 'week') {
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($period === 'month') {
            $query->whereMonth('created_at', Carbon::now()->month);
        }

        return [
            'total_bookings' => $query->count(),
            'total_revenue' => $query->sum('final_amount'),
            'paid_amount' => $query->sum('paid_amount'),
            'pending_amount' => $query->sum('due_amount'),
            'tour_bookings' => $query->where('service_type', 'TOURS')->count(),
            'car_rentals' => $query->where('service_type', 'CAR_RENTAL')->count(),
        ];
    }
}
