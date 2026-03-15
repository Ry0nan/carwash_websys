<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrderItem extends Model
{
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'job_order_id',
        'service_id',
        'item_name',
        'unit_price',
        'price_status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class, 'job_order_id', 'job_order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }
}
