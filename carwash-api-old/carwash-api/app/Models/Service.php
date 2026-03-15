<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $primaryKey = 'service_id';

    protected $fillable = [
        'service_name',
        'vehicle_category',
        'service_group',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function pricing(): HasMany
    {
        return $this->hasMany(ServicePricing::class, 'service_id', 'service_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->whereIn('vehicle_category', [$category, 'BOTH']);
    }
}
