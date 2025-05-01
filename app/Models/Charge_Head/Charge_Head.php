<?php


namespace App\Models\Charge_Head;


use App\User;
use Illuminate\Database\Eloquent\Model;

class Charge_Head extends Model
{
    protected $table = "charge_heads";

    protected $casts = [
        'id' => 'integer',
        ];
}
