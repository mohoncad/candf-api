<?php


namespace App\Models\Location;


use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = "locations";

    protected $casts = [
        'id' => 'integer',
        ];
}
