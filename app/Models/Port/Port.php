<?php


namespace App\Models\Port;


use Illuminate\Database\Eloquent\Model;

class Port extends Model
{
    protected $table = "ports";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        ];
}
