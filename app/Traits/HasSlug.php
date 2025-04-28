<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Boot the trait
     */
    protected static function bootHasSlug()
    {
        static::creating(function ($model) {
            if (!$model->slug) {
                $model->generateSlug();
            }
        });

        static::updating(function ($model) {
            // If name changed but slug didn't, regenerate slug
            if ($model->isDirty('name') && !$model->isDirty('slug')) {
                $model->generateSlug();
            }
        });
    }

    /**
     * Generate a unique slug
     */
    public function generateSlug()
    {
        $slug = $this->slug ?? Str::slug($this->name);
        $originalSlug = $slug;
        $count = 1;

        // Check if slug exists
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $count++;
        }

        $this->slug = $slug;
    }

    /**
     * Check if slug exists
     */
    protected function slugExists($slug)
    {
        return static::withTrashed()
            ->where('slug', $slug)
            ->where('id', '!=', $this->id ?? 0)
            ->exists();
    }
}
