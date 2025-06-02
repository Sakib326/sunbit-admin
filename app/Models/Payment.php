<?php

// filepath: app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'booking_id', 'payment_sequence', 'payment_type', 'payer_type', 'payer_id',
        'amount', 'currency', 'payment_method', 'gateway_transaction_id',
        'gateway_response', 'gateway_reference', 'payment_reference',
        'status', 'failure_reason', 'receipt_number', 'terminal_id',
        'payment_details', 'notes', 'admin_override', 'override_reason',
        'processed_by', 'payment_date', 'gateway_callback_at'
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'payment_details' => 'array',
        'payment_date' => 'datetime',
        'gateway_callback_at' => 'datetime',
        'amount' => 'decimal:2',
        'admin_override' => 'boolean',
    ];

    protected $appends = [
        'formatted_amount',
        'payment_method_label',
        'status_color',
        'is_refundable'
    ];

    // === RELATIONSHIPS ===
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // === ATTRIBUTES ===
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . ($this->currency ?? 'BDT');
    }

    public function getPaymentMethodLabelAttribute()
    {
        return match($this->payment_method) {
            'cash' => 'Cash',
            'card_terminal' => 'Card Terminal',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online Payment',
            'cheque' => 'Cheque',
            default => ucfirst($this->payment_method)
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'completed' => 'success',
            'pending' => 'warning',
            'processing' => 'info',
            'failed' => 'danger',
            'cancelled' => 'gray',
            'refunded' => 'primary',
            default => 'gray'
        };
    }

    public function getIsRefundableAttribute()
    {
        return $this->status === 'completed' &&
               $this->payment_type !== 'refund' &&
               $this->amount > 0;
    }

    // === SCOPES ===
    public function scopeCompleted(Builder $query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeToday(Builder $query)
    {
        return $query->whereDate('payment_date', Carbon::today());
    }

    public function scopeByMethod(Builder $query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeCash(Builder $query)
    {
        return $query->where('payment_method', 'cash');
    }

    public function scopeDigital(Builder $query)
    {
        return $query->whereIn('payment_method', ['bkash', 'nagad', 'rocket', 'online']);
    }

    // === BUSINESS LOGIC ===
    public function canRefund()
    {
        return $this->is_refundable &&
               $this->booking->canRefund() &&
               !$this->hasRefund();
    }

    public function hasRefund()
    {
        return self::where('booking_id', $this->booking_id)
                  ->where('payment_type', 'refund')
                  ->where('gateway_reference', $this->id)
                  ->exists();
    }

    public function markAsCompleted($processedBy = null)
    {
        $this->update([
            'status' => 'completed',
            'processed_by' => $processedBy ?? auth()->id(),
            'payment_date' => now()
        ]);

        // Update booking payment amounts
        $this->booking->increment('paid_amount', $this->amount);
        $this->booking->updatePaymentStatus();

        return $this;
    }

    public function markAsFailed($reason = null)
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'processed_by' => auth()->id()
        ]);

        return $this;
    }

    // === AUTO PAYMENT REFERENCE ===
    public static function generatePaymentReference($bookingReference, $sequence = null)
    {
        $sequence = $sequence ?? (Payment::where('booking_id', request()->booking_id)->count() + 1);
        return "{$bookingReference}-P{$sequence}";
    }

    // === EVENT HANDLING ===
    protected static function booted()
    {
        static::creating(function ($payment) {
            if (empty($payment->payment_reference)) {
                $booking = Booking::find($payment->booking_id);
                $sequence = Payment::where('booking_id', $payment->booking_id)->count() + 1;
                $payment->payment_reference = self::generatePaymentReference($booking->booking_reference, $sequence);
                $payment->payment_sequence = $sequence;
            }

            if (empty($payment->currency)) {
                $payment->currency = 'BDT';
            }

            if (empty($payment->payment_date)) {
                $payment->payment_date = now();
            }
        });

        static::updated(function ($payment) {
            if ($payment->wasChanged('status') && $payment->status === 'completed') {
                $payment->booking->updatePaymentStatus();
            }
        });
    }

    // === HELPER METHODS ===
    public static function getPaymentStats($period = 'month')
    {
        $query = self::completed();

        if ($period === 'today') {
            $query->whereDate('payment_date', Carbon::today());
        } elseif ($period === 'week') {
            $query->whereBetween('payment_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($period === 'month') {
            $query->whereMonth('payment_date', Carbon::now()->month);
        }

        return [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'cash_payments' => $query->where('payment_method', 'cash')->sum('amount'),
            'digital_payments' => $query->whereIn('payment_method', ['bkash', 'nagad', 'rocket'])->sum('amount'),
            'card_payments' => $query->where('payment_method', 'card_terminal')->sum('amount'),
        ];
    }

    public function getReceiptData()
    {
        return [
            'payment_id' => $this->id,
            'payment_reference' => $this->payment_reference,
            'booking_reference' => $this->booking->booking_reference,
            'customer_name' => $this->booking->customer_name,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method_label,
            'payment_date' => $this->payment_date->format('d M Y, g:i A'),
            'status' => ucfirst($this->status),
            'receipt_number' => $this->receipt_number,
            'processed_by' => $this->processedBy->name ?? 'System'
        ];
    }
}
