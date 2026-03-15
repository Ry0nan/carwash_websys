<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $primaryKey = 'vehicle_id';

    protected $fillable = [
        'customer_id',
        'plate_number',
        'vehicle_category',
        'vehicle_size',
        'vehicle_type',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class, 'vehicle_id', 'vehicle_id');
    }
}
