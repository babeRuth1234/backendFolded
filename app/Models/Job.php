<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Job extends Model
{
    protected $fillable = [
        'user_id',
        'status', // 'intake', 'in_progress', 'ready', 'completed'
        'items', // array of embedded category items: [['category_id' => '...', 'name' => 'Shirts', 'quantity' => 2, 'price_per_unit' => 5, 'total' => 10]]
        'total_price',
        'subtotal',
        'discount_applied', // numeric discount amount applied
        'return_date',
        'pickup_window',
        'payment_status', // 'pending', 'paid'
        'paystack_reference',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total_price' => 'float',
            'subtotal' => 'float',
            'discount_applied' => 'float',
            'return_date' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
