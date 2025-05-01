<?php

namespace App\Models\Bill;

use Illuminate\Database\Eloquent\Model;

class bill_summary_list extends Model
{
    protected $table = "bill_summary_lists";

    protected $casts = [
        'summary_id' => 'integer',
        'CompanyID' => 'integer',
        'BranchID' => 'integer',
        'user_id' => 'integer',
        'client_id' => 'integer',
        'supplier_id' => 'integer',
        'CreatedBy' => 'integer',
        'DeletedBy' => 'integer',
        'UpdatedBy' => 'integer'
    ];
}
