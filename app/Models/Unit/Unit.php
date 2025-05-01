<?php


namespace App\Models\Unit;


use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = "units";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        ];
}
