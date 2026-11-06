<?php

namespace App\Models;

use App\Enums\ItemType;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['slug', 'name', 'type', 'power'];

    protected $casts = [
        'type' => ItemType::class,
    ];
}
