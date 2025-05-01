<?php


namespace App\Models\ClientGroups;


use Illuminate\Database\Eloquent\Model;

class ClientGroups extends Model
{
    protected $table = "client_groups";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        ];
}
