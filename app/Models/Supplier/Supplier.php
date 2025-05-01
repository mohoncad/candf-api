<?php


namespace App\Models\Supplier;


use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = "suppliers";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        ];
}
