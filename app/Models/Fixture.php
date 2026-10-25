<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fixture extends Model
{
    protected $guarded = [];

    protected $casts = [
        'kickoff' => 'datetime',
        'odds' => 'array',
    ];
}