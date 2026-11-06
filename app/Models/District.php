<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = ['slug', 'name', 'strength_bonus', 'energy_bonus'];
}
