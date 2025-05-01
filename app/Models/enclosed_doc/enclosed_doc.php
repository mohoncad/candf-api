<?php


namespace App\Models\enclosed_doc;


use Illuminate\Database\Eloquent\Model;

class enclosed_doc extends Model
{
    protected $table = "enclosed_docs";
    protected $primaryKey = "id";

    protected $casts = [
        'id' => 'integer',
        ];
}
