<?php


namespace App\Models\Bank;


use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $table = "bank_list";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        ];
}
