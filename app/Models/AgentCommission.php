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
    ];

    /**
     * Get the agent (user) this commission applies to.
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Get the user who created this commission.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this commission.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for a specific service.
     */
    public function scopeForService($query, $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope for a specific agent.
     */
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }
}
