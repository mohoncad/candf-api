<?php


namespace App\Models\Vehicle;


use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = "vehicles";

    protected $casts = [
        'id' => 'integer',
        ];
}
