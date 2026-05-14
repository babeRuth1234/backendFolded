<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'price_per_unit',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'float',
        ];
    }
}
