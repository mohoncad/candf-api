<?php

namespace App\Models\Bill;

use Illuminate\Database\Eloquent\Model;

class bill_entry_list extends Model
{
    protected $table = "bill_entry_lists";
    protected $fillable = ['enc_docs','particulars_charges','attachments'];

    protected $casts = [
        'id' => 'integer',
        'CompanyID' => 'integer',
        'BranchID' => 'integer',
        'user_id' => 'integer',
        'client_id' => 'integer',
        'supplier_id' => 'integer',
        'port_id' => 'integer',
        'currency_id' => 'integer',
        'client_bank_name' => 'integer',
        'unit_id' => 'integer',
        'CreatedBy' => 'integer',
        'DeletedBy' => 'integer',
        'UpdatedBy' => 'integer'
    ];
}
