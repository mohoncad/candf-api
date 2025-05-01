<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = "registered_companies";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'IsActive',
        'Name',
        'Size',
        'Address1',
        'Address2',
        'Address3',
        'Email',
        'ContactNumber',
        'WebAddress',
        'Logo',
        'IsDemo',
        'LicenseFee',
        'ServiceFee',
        'Status',
        'CreatedBy',
        'UpdatedBy',
        'DeletedBy',
        'CreatedAt',
        'UpdatedAt',
        'DeletedAt'
    ];

    protected $casts = [
        'id' => 'integer',
        'IsInvoiceNumberTypeSelected' => 'integer',
        ];
}
