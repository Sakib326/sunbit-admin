<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class State extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasSlug;

    protected $fillable = [
        'country_id',
        'name',
        'slug',
        'code',
        'description',           // ✅ ADD THIS
        'is_top_destination',    // ✅ ADD THIS
        'image',
        'status'
    ];

    protected $casts = [
        'is_top_destination' => 'boolean',  // ✅ ADD THIS CAST
        'status' => 'string',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function zellas(): HasMany
    {
        return $this->hasMany(Zella::class);
    }

    // ✅ ADD SCOPE FOR TOP DESTINATIONS
    public function scopeTopDestinations($query)
    {
        return $query->where('is_top_destination', true)->where('status', 'active');
    }

    // ✅ ADD SCOPE FOR ACTIVE STATES
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ✅ ADD METHOD TO GET EXCERPT FROM DESCRIPTION
    public function getDescriptionExcerpt(int $length = 150): string
    {
        if (!$this->description) {
            return '';
        }

        return strlen($this->description) > $length
            ? substr(strip_tags($this->description), 0, $length) . '...'
            : strip_tags($this->description);
    }
}
