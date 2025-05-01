<?php


namespace App\Models\SysCode;


use Illuminate\Database\Eloquent\Model;

class SysCode extends Model
{
    protected $table = "sys_codes";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;
}
