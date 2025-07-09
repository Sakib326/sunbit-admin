<?php

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

    protected $casts = [
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
        'base_price_adult' => 'decimal:2',
        'base_price_child' => 'decimal:2',
        'agent_commission_percent' => 'decimal:2',
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

    // === BOOKING RELATIONSHIPS ===
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'tour_package_id');
    }

    public function tourBookingDetails()
    {
        return $this->hasMany(TourBookingDetail::class, 'tour_package_id');
    }

    // === LOCATION RELATIONSHIPS ===
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

    // === HELPER METHODS ===
    public function getFromLocationName()
    {
        if ($this->fromUpazilla) {
            return $this->fromUpazilla->name;
        }
        if ($this->fromZella) {
            return $this->fromZella->name;
        }
        if ($this->fromState) {
            return $this->fromState->name;
        }
        if ($this->fromCountry) {
            return $this->fromCountry->name;
        }
        return $this->from_location_details ?? 'Not specified';
    }

    public function getToLocationName()
    {
        if ($this->toUpazilla) {
            return $this->toUpazilla->name;
        }
        if ($this->toZella) {
            return $this->toZella->name;
        }
        if ($this->toState) {
            return $this->toState->name;
        }
        if ($this->toCountry) {
            return $this->toCountry->name;
        }
        return $this->to_location_details ?? 'Not specified';
    }

    public function getFullFromLocation()
    {
        $parts = [];
        if ($this->fromUpazilla) $parts[] = $this->fromUpazilla->name;
        if ($this->fromZella && $this->fromZella->name !== $this->fromUpazilla?->name) {
            $parts[] = $this->fromZella->name;
        }
        if ($this->fromState) $parts[] = $this->fromState->name;
        if ($this->fromCountry) $parts[] = $this->fromCountry->name;

        return implode(', ', $parts) ?: ($this->from_location_details ?? 'Not specified');
    }

    public function getFullToLocation()
    {
        $parts = [];
        if ($this->toUpazilla) $parts[] = $this->toUpazilla->name;
        if ($this->toZella && $this->toZella->name !== $this->toUpazilla?->name) {
            $parts[] = $this->toZella->name;
        }
        if ($this->toState) $parts[] = $this->toState->name;
        if ($this->toCountry) $parts[] = $this->toCountry->name;

        return implode(', ', $parts) ?: ($this->to_location_details ?? 'Not specified');
    }

    public function getTourRoute()
    {
        $from = $this->getFromLocationName();
        $to = $this->getToLocationName();
        return "{$from} → {$to}";
    }

    public function getTourTypeLabel()
    {
        return match($this->tour_type) {
            'domestic' => 'Domestic Tour',
            'international' => 'International Tour',
            'local' => 'Local Tour',
            default => ucfirst($this->tour_type ?? 'Tour')
        };
    }

    // === ACCESSORS FOR COMPATIBILITY ===
    public function getNameAttribute()
    {
        return $this->title;
    }

    public function getDurationDaysAttribute()
    {
        return $this->number_of_days;
    }

    public function getDurationNightsAttribute()
    {
        return $this->number_of_nights;
    }

    // === SCOPES ===
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function scopeByTourType($query, $type)
    {
        return $query->where('tour_type', $type);
    }
}