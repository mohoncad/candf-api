<?php

namespace App\Models\Bill;

use Illuminate\Database\Eloquent\Model;

class transport_bill_list extends Model
{
    protected $table = "transport_bill_lists";

    protected $primaryKey = 'bill_id';

    protected $casts = [
        'bill_id' => 'integer',
        'CompanyID' => 'integer',
        'BranchID' => 'integer',
        'user_id' => 'integer',
        'client_id' => 'integer',
        'unit_id' => 'integer',
        'supplier_id' => 'integer',
        'CreatedBy' => 'integer',
        'DeletedBy' => 'integer',
        'UpdatedBy' => 'integer'
    ];
}
