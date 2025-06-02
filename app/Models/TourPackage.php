<?php

// filepath: app/Models/TourPackage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class TourPackage extends Model
{
    use HasFactory;

    protected $table = 'tour_packages';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'category_id',
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'description',
        'highlights',
        'tour_schedule',
        'whats_included',
        'whats_excluded',
        'area_map_url',
        'guide_pdf_url',
        'is_featured',
        'is_popular',
        'number_of_days',
        'number_of_nights',
        'base_price_adult',
        'base_price_child',
        'max_booking_per_day',
        'agent_commission_percent',
        // Location fields
        'from_country_id', 'from_state_id', 'from_zella_id', 'from_upazilla_id',
        'to_country_id', 'to_state_id', 'to_zella_id', 'to_upazilla_id',
        'from_location_details', 'to_location_details', 'tour_type',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?? (string) Str::uuid();
        });
    }

    // === EXISTING RELATIONSHIPS ===
    public function category()
    {
        return $this->belongsTo(TourCategory::class, 'category_id');
    }

    public function itineraries()
    {
        return $this->hasMany(TourPackageItinerary::class, 'tour_package_id');
    }

    public function galleries()
    {
        return $this->hasMany(TourPackageGallery::class, 'tour_package_id');
    }

    public function faqs()
    {
        return $this->hasMany(TourPackageFaq::class, 'tour_package_id');
    }

    public function bookingLimits()
    {
        return $this->hasMany(TourPackageBookingLimit::class, 'tour_package_id');
    }

    // === NEW LOCATION RELATIONSHIPS ===
    // FROM Location relationships
    public function fromCountry()
    {
        return $this->belongsTo(Country::class, 'from_country_id');
    }

    public function fromState()
    {
        return $this->belongsTo(State::class, 'from_state_id');
    }

    public function fromZella()
    {
        return $this->belongsTo(Zella::class, 'from_zella_id');
    }

    public function fromUpazilla()
    {
        return $this->belongsTo(Upazilla::class, 'from_upazilla_id');
    }

    // TO Location relationships
    public function toCountry()
    {
        return $this->belongsTo(Country::class, 'to_country_id');
    }

    public function toState()
    {
        return $this->belongsTo(State::class, 'to_state_id');
    }

    public function toZella()
    {
        return $this->belongsTo(Zella::class, 'to_zella_id');
    }

    public function toUpazilla()
    {
        return $this->belongsTo(Upazilla::class, 'to_upazilla_id');
    }

    // === LOCATION METHODS ===
    public function getFromLocationName()
    {
        $parts = [];
        if ($this->fromUpazilla) {
            $parts[] = $this->fromUpazilla->name;
        }
        if ($this->fromZella && $this->fromZella->name !== $this->fromUpazilla?->name) {
            $parts[] = $this->fromZella->name;
        }
        if ($this->fromState) {
            $parts[] = $this->fromState->name;
        }
        if ($this->fromCountry) {
            $parts[] = $this->fromCountry->name;
        }

        return !empty($parts) ? implode(', ', $parts) : 'Location not specified';
    }

    public function getToLocationName()
    {
        $parts = [];
        if ($this->toUpazilla) {
            $parts[] = $this->toUpazilla->name;
        }
        if ($this->toZella && $this->toZella->name !== $this->toUpazilla?->name) {
            $parts[] = $this->toZella->name;
        }
        if ($this->toState) {
            $parts[] = $this->toState->name;
        }
        if ($this->toCountry) {
            $parts[] = $this->toCountry->name;
        }

        return !empty($parts) ? implode(', ', $parts) : 'Location not specified';
    }

    public function getFullFromLocation()
    {
        $location = $this->getFromLocationName();
        return $this->from_location_details ? $this->from_location_details . ', ' . $location : $location;
    }

    public function getFullToLocation()
    {
        $location = $this->getToLocationName();
        return $this->to_location_details ? $this->to_location_details . ', ' . $location : $location;
    }

    public function getTourTypeLabel()
    {
        return match($this->tour_type) {
            'domestic' => 'Domestic Tour',
            'international' => 'International Tour',
            'local' => 'Local Tour',
            default => 'Domestic Tour'
        };
    }

    public function getTourRoute()
    {
        $from = $this->getFromLocationName();
        $to = $this->getToLocationName();

        if ($from === 'Location not specified' && $to === 'Location not specified') {
            return 'Route not specified';
        }

        if ($from === $to || $to === 'Location not specified') {
            return $from;
        }

        return "{$from} → {$to}";
    }

    // === EXISTING METHODS ===
    public function getMaxBookingForDate($date)
    {
        $special = $this->bookingLimits()->where('date', $date)->first();
        return $special?->max_booking ?? $this->max_booking_per_day;
    }
}
