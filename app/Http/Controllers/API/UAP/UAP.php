<?php


namespace App\Http\Controllers\API\UAP;


class UAP extends \App\Http\Controllers\Controller
{
    //from the `module_list` table
    static $ModuleCodes = [
        1 => "USER_ROLE",
        2 => "USER",
        3 => "BRANCH",
        4 => "BANK",
        5 => "CLIENT_GROUPS",
        6 => "CURRENCY",
        7 => "PORT",
        8 => "UNIT",
        9 => "CLIENT",
        10 => "SUPPLIER",
        11 => "IMPORT",
        12 => "EXPORT",
        13 => "TRANSPORT",
        14 => "BILL_SUMMARY",
        15 => "COMPANY",
        16 => "BILL_PAYMENT"
    ];

    //from the `role_permissions` table
    static $RolePermissionNames = [
        1 => "ModuleAccess",
        2 => "View",
        3 => "Add",
        4 => "Edit",
        5 => "Delete",
        6 => "ActualAmountView",
        7 => "CustomerAmountView",
        8 => "Trash",
        9 => "Restore",
        10 => "DeleteForever"
    ];
}
