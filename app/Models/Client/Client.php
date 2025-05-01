<?php


namespace App\Models\Client;


use App\User;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = "clients";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    function deletedBy(){
        return $this->belongsTo(User::class,'DeletedBy');
    }

    protected $casts = [
        'id' => 'integer',
        'CompanyID' => 'integer',
        'ClientGroupID' => 'integer'
        ];
}
