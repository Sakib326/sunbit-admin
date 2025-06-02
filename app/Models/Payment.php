<?php

// filepath: app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'booking_id', 'payment_sequence', 'payment_type', 'payer_type', 'payer_id',
        'amount', 'currency', 'payment_method', 'gateway_transaction_id', 'gateway_response',
        'gateway_reference', 'payment_reference', 'status', 'failure_reason',
        'receipt_number', 'terminal_id', 'payment_details', 'notes', 'admin_override',
        'override_reason', 'processed_by', 'payment_date', 'gateway_callback_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'gateway_callback_at' => 'datetime',
        'admin_override' => 'boolean',
        'gateway_response' => 'array',
        'payment_details' => 'array',
    ];

    // === RELATIONSHIPS ===
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // === BOOT METHOD ===
    protected static function booted()
    {
        static::creating(function ($payment) {
            if (empty($payment->payment_reference)) {
                $payment->payment_reference = self::generatePaymentReference();
            }

            // Auto-set payment sequence
            if (empty($payment->payment_sequence)) {
                $payment->payment_sequence = self::where('booking_id', $payment->booking_id)->count() + 1;
            }
        });

        static::updated(function ($payment) {
            if ($payment->status === 'completed') {
                $payment->booking->updatePaymentStatus();
            }
        });

        static::created(function ($payment) {
            if ($payment->status === 'completed') {
                $payment->booking->updatePaymentStatus();
            }
        });
    }

    // === METHODS ===
    public static function generatePaymentReference()
    {
        $year = date('y');
        $month = date('m');

        $count = self::whereYear('created_at', date('Y'))
                    ->whereMonth('created_at', date('m'))
                    ->count() + 1;

        return 'PAY' . $year . $month . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function markAsCompleted()
    {
        $this->update(['status' => 'completed']);
        $this->booking->updatePaymentStatus();
    }

    public function canBeEdited()
    {
        return !in_array($this->status, ['completed', 'refunded']);
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function canRefund()
    {
        return $this->status === 'completed';
    }

    // === SCOPES ===
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByBooking($query, $bookingId)
    {
        return $query->where('booking_id', $bookingId);
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }
}
