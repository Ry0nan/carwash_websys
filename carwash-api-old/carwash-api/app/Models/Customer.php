<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'full_name',
        'contact_number',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'customer_id', 'customer_id');
    }

    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class, 'customer_id', 'customer_id');
    }
}
