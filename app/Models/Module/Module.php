<?php

namespace App\Models\Module;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = "module_list";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        ];
}
