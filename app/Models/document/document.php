<?php

namespace App\Models\document;

use Illuminate\Database\Eloquent\Model;

class document extends Model
{
    protected $table = "enclosed_docs";

    protected $casts = [
        'id' => 'integer',
        ];
}
