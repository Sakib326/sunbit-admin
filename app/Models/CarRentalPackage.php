<?php

// filepath: app/Models/CarRentalPackage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CarRentalPackage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'title', 'slug', 'description', 'car_brand', 'car_model',
        'pax_capacity', 'transmission', 'air_condition', 'chauffeur_option',
        'daily_price', 'agent_commission_percent', 'total_cars',
        'featured_image', 'status'
    ];

    protected $casts = [
        'daily_price' => 'decimal:2',
        'agent_commission_percent' => 'decimal:2',
    ];

    // === RELATIONSHIPS ===
    public function locations()
    {
        return $this->hasMany(CarRentalPackageLocation::class);
    }

    public function bookingLimits()
    {
        return $this->hasMany(CarRentalBookingLimit::class);
    }

    public function bookingsDetail()
    {
        return $this->hasMany(CarRentalBookingDetail::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'car_rental_package_id');
    }

    // === LOCATION METHODS ===
    public function getAvailableCountries()
    {
        return Country::whereIn('id', $this->locations()->pluck('country_id')->filter())->get();
    }

    public function getAvailableStates()
    {
        return State::whereIn('id', $this->locations()->pluck('state_id')->filter())->get();
    }

    public function getAvailableZellas()
    {
        return Zella::whereIn('id', $this->locations()->pluck('zella_id')->filter())->get();
    }

    public function getAvailableUpazillas()
    {
        return Upazilla::whereIn('id', $this->locations()->pluck('upazilla_id')->filter())->get();
    }

    public function isAvailableInLocation($countryId = null, $stateId = null, $zellaId = null, $upazillaId = null)
    {
        $query = $this->locations();

        if ($upazillaId) {
            return $query->where('upazilla_id', $upazillaId)->exists();
        }
        if ($zellaId) {
            return $query->where('zella_id', $zellaId)->exists();
        }
        if ($stateId) {
            return $query->where('state_id', $stateId)->exists();
        }
        if ($countryId) {
            return $query->where('country_id', $countryId)->exists();
        }

        return false;
    }

    // === AVAILABILITY METHODS ===
    public function getAvailableCarsForDate($date)
    {
        // Check for special limit on this date
        $bookingLimit = $this->bookingLimits()->where('date', $date)->first();
        $maxBookings = $bookingLimit ? $bookingLimit->max_booking_override : $this->total_cars;

        // Count existing bookings for this date
        $bookedCars = $this->bookingsDetail()
            ->whereHas('booking', function ($query) use ($date) {
                $query->where('service_date', $date)
                      ->where('status', '!=', 'cancelled');
            })
            ->count();

        return max(0, $maxBookings - $bookedCars);
    }

    public function getPriceForDate($date)
    {
        $bookingLimit = $this->bookingLimits()->where('date', $date)->first();

        if ($bookingLimit && $bookingLimit->special_daily_price) {
            return $bookingLimit->special_daily_price;
        }

        return $this->daily_price;
    }

    public function getFullCarName()
    {
        return "{$this->car_brand} {$this->car_model}";
    }

    public function getServiceAreaText()
    {
        $locations = $this->locations()->with(['country', 'state', 'zella', 'upazilla'])->get();

        if ($locations->isEmpty()) {
            return 'Service area not specified';
        }

        $areas = $locations->map(function ($location) {
            $parts = [];

            if ($location->upazilla) {
                $parts[] = $location->upazilla->name;
            }
            if ($location->zella && $location->zella->name !== $location->upazilla?->name) {
                $parts[] = $location->zella->name;
            }
            if ($location->state) {
                $parts[] = $location->state->name;
            }

            return implode(', ', $parts);
        })->take(3)->join(' | ');

        if ($locations->count() > 3) {
            $areas .= ' + ' . ($locations->count() - 3) . ' more areas';
        }

        return $areas;
    }

    // === SCOPES ===
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInLocation($query, $countryId = null, $stateId = null, $zellaId = null, $upazillaId = null)
    {
        return $query->whereHas('locations', function ($locationQuery) use ($countryId, $stateId, $zellaId, $upazillaId) {
            if ($upazillaId) {
                $locationQuery->where('upazilla_id', $upazillaId);
            } elseif ($zellaId) {
                $locationQuery->where('zella_id', $zellaId);
            } elseif ($stateId) {
                $locationQuery->where('state_id', $stateId);
            } elseif ($countryId) {
                $locationQuery->where('country_id', $countryId);
            }
        });
    }

    public function scopeWithChauffeur($query)
    {
        return $query->where('chauffeur_option', 'with_chauffeur');
    }

    public function scopeSelfDrive($query)
    {
        return $query->where('chauffeur_option', 'without_chauffeur');
    }

    public function scopeByCapacity($query, $capacity)
    {
        return $query->where('pax_capacity', $capacity);
    }


}
