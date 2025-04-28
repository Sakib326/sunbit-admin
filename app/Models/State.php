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

    protected $fillable = ['country_id', 'name', 'slug', 'code', 'status'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function zellas(): HasMany
    {
        return $this->hasMany(Zella::class);
    }
}
