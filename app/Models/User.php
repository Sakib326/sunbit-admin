<?php

// filepath: app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', // Add 'phone' here
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }

    public function agentCommissions()
    {
        return $this->hasMany(AgentCommission::class, 'agent_id');
    }

    // Add other relationships as needed
    public function bookingsAsCustomer()
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function bookingsAsAgent()
    {
        return $this->hasMany(Booking::class, 'agent_id');
    }

    public function bookingsProcessed()
    {
        return $this->hasMany(Booking::class, 'booked_by');
    }

    public function carRentalBookings()
    {
        return $this->hasMany(Booking::class, 'customer_id')->where('service_type', 'CAR_RENTAL');
    }

    public function carRentalBookingsAsAgent()
    {
        return $this->hasMany(Booking::class, 'agent_id')->where('service_type', 'CAR_RENTAL');
    }

    public function tourBookings()
    {
        return $this->hasMany(Booking::class, 'customer_id')->where('service_type', 'TOURS');
    }

    public function tourBookingsAsAgent()
    {
        return $this->hasMany(Booking::class, 'agent_id')->where('service_type', 'TOURS');
    }
}
