<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'commission_percent',
        'commission_amount',
        'agent_id',
        'created_by',
        'updated_by',
    ];

    // ENUM values for service
    public const SERVICES = [
        'FLIGHT',
        'HOTEL',
        'TRANSFER',
        'TOURS',
        'CRUISE',
        'TRANSPORT',
        'VISA',
        'CAR_RENTAL',
    ];

    protected $casts = [
        'service' => 'string',
        'commission_percent' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Display service as readable label
    public function getServiceLabelAttribute()
    {
        return ucfirst(strtolower($this->service));
    }

    // Calculate commission for a given amount
    public function calculateCommission($amount)
    {
        if ($this->commission_percent !== null) {
            return round($amount * ($this->commission_percent / 100), 2);
        }
        if ($this->commission_amount !== null) {
            return $this->commission_amount;
        }
        return 0;
    }

    // Scope for a specific service
    public function scopeForService($query, $service)
    {
        return $query->where('service', $service);
    }

    // Scope for a specific agent
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }
}
