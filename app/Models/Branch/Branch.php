<?php


namespace App\Models\Branch;


use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = "branch_list";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        ];
}
