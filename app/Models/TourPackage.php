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
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?? (string) Str::uuid();
        });
    }

    // Relationships
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

    /**
     * Get the max booking for a specific date.
     */
    public function getMaxBookingForDate($date)
    {
        $special = $this->bookingLimits()->where('date', $date)->first();
        return $special?->max_booking ?? $this->max_booking_per_day;
    }
}
