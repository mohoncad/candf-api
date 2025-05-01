<?php

namespace App\Models\UserRole;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = "user_roles";
    protected $primaryKey = "id";
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        "CompanyID",
        "IsActive",
        "IsDeleted",
        "RoleName",
        "Description",
        "CreatedByAdmin",
        "CreatedBy",
        "CreatedAt",
        "UpdatedByAdmin",
        "UpdatedBy",
        "UpdatedAt",
        "DeletedByAdmin",
        "DeletedBy",
        "DeletedAt"
    ];

    protected $casts = [
        'id' => 'integer',
        ];
}
