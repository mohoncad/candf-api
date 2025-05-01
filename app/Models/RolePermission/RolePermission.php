<?php

namespace App\Models\RolePermission;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $table = "role_permissions";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'UserRoleID',
        'ModuleCode',
        'ModuleAccess',
        'View',
        'Add',
        'Edit',
        'Delete',
        'ActualAmountView',
        'CustomerAmountView'
    ];
}
