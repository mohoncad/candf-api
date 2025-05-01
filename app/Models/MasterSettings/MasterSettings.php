<?php

namespace App\Models\MasterSettings;

use Illuminate\Database\Eloquent\Model;

class MasterSettings extends Model
{
    protected $table = "master_settings";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;
}
