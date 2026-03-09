<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOrder extends Model
{
    protected $primaryKey = 'job_order_id';

    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'washboy_name',
        'payment_mode',
        'status',
        'leave_vehicle',
        'waiver_accepted',
        'waiver_accepted_at',
        'completed_at',
    ];

    protected $casts = [
        'leave_vehicle'      => 'boolean',
        'waiver_accepted'    => 'boolean',
        'waiver_accepted_at' => 'datetime',
        'completed_at'       => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JobOrderItem::class, 'job_order_id', 'job_order_id');
    }

    /**
     * Total of all priced items (TBA items are ignored until quoted).
     */
    public function computeTotal(): float
    {
        return (float) $this->items()->whereNotNull('unit_price')->sum('unit_price');
    }

    /**
     * True when the order still has at least one TBA item.
     */
    public function hasTba(): bool
    {
        return $this->items()->where('price_status', 'TBA')->exists();
    }
}
