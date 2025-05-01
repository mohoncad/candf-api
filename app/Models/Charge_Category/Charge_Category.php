<?php


namespace App\Models\Charge_Category;


use App\User;
use Illuminate\Database\Eloquent\Model;

class Charge_Category extends Model
{
    protected $table = "charge_categories";

    protected $casts = [
        'id' => 'integer',
        ];

}
