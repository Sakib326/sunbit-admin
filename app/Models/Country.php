<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasSlug;

    protected $fillable = ['name', 'slug', 'code', 'currency', 'currency_symbol', 'status'];

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }
}
