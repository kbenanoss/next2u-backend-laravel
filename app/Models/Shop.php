<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'category_id', 'address', 'latitude', 'longitude',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

