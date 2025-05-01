<?php

namespace App\Http\Controllers\API\UAP;

use App\Models\Module\Module;
use App\Models\RolePermission\RolePermission;
use App\Models\UserRole\UserRole;
use App\User;
use Illuminate\Http\Request;
use \Exception;

class UAPController extends UAP
{
    public function __construct()
    {

    }


    /**
     * Get the permission list record using the module code
     * Used only in user access permission setup page,
     * Not used for identifying a module's permissions
     * @param int $RoleID
     * @param string $ModuleCode
     * @return mixed
     */
    private static function getPermissionsByModuleCode(int $RoleID, string $ModuleCode)
    {
        return RolePermission::where('UserRoleID', '=', $RoleID)->where('ModuleCode', '=', $ModuleCode)->first();
    }


    /**
     * Recursion for creating a object of all the modules with their permission records
     * Used for user access permissions setup page only
     * @param $RoleID
     * @param string $ParentCode
     * @return mixed
     */
    private function ModuleListingObject(int $RoleID, string $ParentCode = '0')
    {
        $Modules = Module::where('ParentCode', '=', $ParentCode)->get();
        foreach ($Modules as $Module) {
            $Module->ParentAccessable = @self::getPermissionsByModuleCode($RoleID, $Module->ParentCode)->ModuleAccess;
            $Module->Permissions = self::getPermissionsByModuleCode($RoleID, $Module->Code);
            $Module->ChildModules = $this->ModuleListingObject($RoleID, $Module->Code);
        }

        return $Modules;
    }


    /**
     * Get all the parent list using a module code
     * @param $ModuleCode
     * @param array $parents_array
     * @return array|mixed
     */
    public static function GetAllParentsByModuleCode(string $ModuleCode, array $parents_array = [])
    {
        $Module = Module::where('Code', '=', $ModuleCode)->first();

        if ($Module->ParentCode != '0') {
            array_push($parents_array, $Module->ParentCode);
            $parents_array = self::GetAllParentsByModuleCode($Module->ParentCode, $parents_array);
        }

        return $parents_array;
    }

    /**
     * Get the permissions of the module using the module code
     * Used anywhere to detect the permissions,
     * Only used for session user
     * @param string $ModuleCode
     * @return mixed
     */
    public static function ModulePermissions(string $ModuleCode)
    {
        $user = auth()->user();
        $user_role_id = $user->UserRoleID;

        $Permissions = false;

        if (!$user->IsSupportUser) {

            /**
             * Check if the user role exists or not
             */
            $UserRole = UserRole::where('id', '=', $user_role_id)->where('IsActive', '=', 1);

            if ($UserRole->exists()) {

                $Permissions = RolePermission::where('UserRoleID', '=', $user_role_id)->where('ModuleCode', '=', $ModuleCode)->first();

                if (empty($Permissions)) {
                    $Permissions = false;
                }

                $Module = Module::where('Code', '=', $ModuleCode)->first();
                if ($Module->HasModuleAccess == 0) {
                    $Permissions = false;
                }


                /**
                 * Check all the parent module's permissions
                 * If they do not have module access permission, we will set @var $Permissions = false
                 */
                $parents = self::GetAllParentsByModuleCode($ModuleCode);
                foreach ($parents as $parent_module_code) {

                    $Module = Module::where('Code', '=', $parent_module_code)->first();

                    $parent_permission = RolePermission::where('UserRoleID', '=', $user_role_id)->where('ModuleCode', '=', $parent_module_code)->first();

                    if (empty($parent_permission) || $parent_permission->ModuleAccess == 0 || $Module->HasModuleAccess == 0) {
                        $Permissions = false;
                    }

                }

            }

        } else {

            $Permissions = true;

        }

        return $Permissions;
    }


    /**
     * Recursion for creating a object of all the modules with their permission records
     * Used for session users only
     * @param string $ParentCode
     * @return mixed
     */
    private function MyModuleListingObject($ParentCode = '0')
    {
        $Modules = Module::where('ParentCode', '=', $ParentCode)->get();
        foreach ($Modules as $Module) {
            $Module->Permissions = self::ModulePermissions($Module->Code);
            $Module->ChildModules = $this->MyModuleListingObject($Module->Code);
        }

        return $Modules;
    }


    /**
     * Get all the modules
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetAllModules(Request $request)
    {
        $__module_permissions = self::ModulePermissions(self::$ModuleCodes['1']);
        if ($__module_permissions === true || (gettype($__module_permissions) === 'object' && $__module_permissions->ModuleAccess)) {

            $role_id = $request->input("role_id");
            $Modules = $this->ModuleListingObject($role_id, '0');


            return response()->json([
                'success' => true,
                'message' => 'All Module List Got Successfully!',
                'module_list' => $Modules
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);

    }


    /**
     * @param $Modules
     * @param $UserRoleID
     */
    public function SaveUAP($UserRoleID, $Modules)
    {

        foreach ($Modules as $Module) {

            $ModuleCode = @$Module['Code'];
            $ChildModules = (array)@$Module['ChildModules'];

            $Permission_id = @$Module['Permissions']['id'];
            $ModuleAccess = (int)@$Module['Permissions']['ModuleAccess'];
            $View = (int)@$Module['Permissions']['View'];
            $Add = (int)@$Module['Permissions']['Add'];
            $Edit = (int)@$Module['Permissions']['Edit'];
            $Delete = (int)@$Module['Permissions']['Delete'];
            $ActualAmountView = (int)@$Module['Permissions']['ActualAmountView'];
            $CustomerAmountView = (int)@$Module['Permissions']['CustomerAmountView'];
            $Trash = (int)@$Module['Permissions']['Trash'];
            $Restore = (int)@$Module['Permissions']['Restore'];
            $DeleteForever = (int)@$Module['Permissions']['DeleteForever'];


            $data = [
                'UserRoleID' => $UserRoleID,
                'ModuleAccess' => $ModuleAccess,
                'ModuleCode' => $ModuleCode,
                'View' => $View,
                'Add' => $Add,
                'Edit' => $Edit,
                'Delete' => $Delete,
                'ActualAmountView' => $ActualAmountView,
                'CustomerAmountView' => $CustomerAmountView,
                'Trash' => $Trash,
                'Restore' => $Restore,
                'DeleteForever' => $DeleteForever,
            ];

            $preRoleP = RolePermission::where('id', '=', $Permission_id)->where('UserRoleID', '=', $UserRoleID)->where('ModuleCode', '=', $ModuleCode);
            if ($preRoleP->exists()) {

                $preRoleP->update($data);

            } else {

                RolePermission::Insert($data);

            }


            if (is_array($ChildModules) && count($ChildModules) > 0) {
                $this->SaveUAP($UserRoleID, $ChildModules);
            }


        }


    }


    public function SetUAP(Request $request)
    {
        $user = auth()->user();
        $RoleID = $request->input("RoleID");
        $Modules = $request->input("Modules");
        $CreatedAt = date("Y-m-d H:i:s", time());

        try {

            $this->SaveUAP($RoleID, $Modules);

            UserRole::where('id', '=', $RoleID)->update([
                'UpdatedBy' => $user->id,
                'UpdatedAt' => $CreatedAt,
            ]);

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Failed! ' . $exception->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned Successfully!',
        ]);
    }


    /**
     * Get only session user's modules
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetMyModules()
    {

        $Modules = $this->MyModuleListingObject();

        return response()->json([
            'success' => true,
            'message' => 'My Module List Got Successfully!',
            'module_list' => $Modules
        ]);

    }


    /**
     * Get all the user roles
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetUserRoles(Request $request)
    {
        $user = auth()->user();
        $__module_permissions = self::ModulePermissions(self::$ModuleCodes['1']);
        if ($__module_permissions === true || (gettype($__module_permissions) === 'object' && $__module_permissions->ModuleAccess)) {

            $search_query = $request->input('search_query');
            $trash_mode = $request->input('trash_mode');
            $trash_mode = $trash_mode === 'true' ? 1 : 0;

            if ($trash_mode === 1) {
                if (!$__module_permissions === true || !$__module_permissions->Trash) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'PERMISSION_DENIED',
                        'message' => 'Access Denied! You need permission to perform this action!',
                    ], 403);
                }
            }

            try {
                $UserRoleList = UserRole::where("CompanyID", "=", $user->CompanyID)->where("IsDeleted", "=", $trash_mode)->orderBy('id', 'DESC');

                if ($search_query !== "") {
                    $UserRoleList->where("RoleName", "LIKE", "%" . $search_query . "%");
                }


                if ($UserRoleList->exists()) {
                    $UserRoleList = $UserRoleList->get();
                    foreach ($UserRoleList as $UserRole) {
                        if ($UserRole->DeletedBy > 0) {
                            $deleted_user = User::where("id", "=", $UserRole->DeletedBy);

                            if($deleted_user->exists()) {
                                $deleted_user = $deleted_user->first();
                                $UserRole->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                            } else {
                                $UserRole->DeletedBy = "User not found!";
                            }

                            $UserRole->DeletedAt = date('d M, Y - h:ia', strtotime($UserRole->DeletedAt));
                        }
                    }
                } else {
                    $UserRoleList = [];
                }

                return response()->json([
                    'success' => true,
                    'message' => 'User role List Got Successfully!',
                    'user_role_list' => $UserRoleList
                ]);
            } catch (Exception $exception) {

                return response()->json([
                    'success' => false,
                    'error_code' => 'DB_ERROR',
                    'message' => 'Failed to get the user role list! ' . $exception->getMessage(),
                ]);

            }

        }


        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }


    /**
     * Save a user role
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function SaveUserRole(Request $request)
    {
        $user = auth()->user();
        $__module_permissions = self::ModulePermissions(self::$ModuleCodes['1']);
        if ($__module_permissions === true || (gettype($__module_permissions) === 'object' && $__module_permissions->ModuleAccess)) {

            $RoleID = $request->input("RoleID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $data = [
                "CompanyID" => $user->CompanyID,
                "RoleName" => $request->input("RoleName"),
                "Description" => $request->input("RoleDescription"),
                "IsActive" => $request->input("RoleIsActive")
            ];


            try {

                $UserRole = UserRole::where('id', '=', $RoleID);
                if ($UserRole->exists()) {

                    if ($__module_permissions === true || $__module_permissions->Edit) {

                        $data["UpdatedBy"] = $user->id;
                        $data["UpdatedAt"] = $CreatedAt;
                        $UserRole->update($data);

                    } else {

                        return response()->json([
                            'success' => false,
                            'error_code' => 'PERMISSION_DENIED',
                            'message' => 'Access Denied! You need permission to perform this action!',
                        ], 403);

                    }

                } else {

                    if ($__module_permissions === true || $__module_permissions->Add) {

                        $data["CreatedBy"] = $user->id;
                        $data["CreatedAt"] = $CreatedAt;
                        UserRole::Insert($data);

                    } else {

                        return response()->json([
                            'success' => false,
                            'error_code' => 'PERMISSION_DENIED',
                            'message' => 'Access Denied! You need permission to perform this action!',
                        ], 403);

                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'User role saved successfully!',
                ]);

            } catch (Exception $exception) {

                return response()->json([
                    'success' => false,
                    'error_code' => 'DB_ERROR',
                    'message' => 'There was a query error! ' . $exception,
                ]);

            }
        }


        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'You have no permission to view or modify this settings!',
        ], 403);
    }


    /**
     * Delete a user role
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeleteUserRole(Request $request)
    {

        $user = auth()->user();
        $__module_permissions = self::ModulePermissions(self::$ModuleCodes['1']);
        if ($__module_permissions === true || (gettype($__module_permissions) === 'object' && $__module_permissions->ModuleAccess)) {

            $trash_mode = $request->input('trash_mode');
            $trash_mode = $trash_mode === 'true' ? 1 : 0;

            if ($trash_mode === 1) {
                if (!$__module_permissions === true || !$__module_permissions->DeleteForever) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'PERMISSION_DENIED',
                        'message' => 'Access Denied! You need permission to perform this action!',
                    ], 403);
                }
            }

            if ($trash_mode === 0 && !($__module_permissions === true || $__module_permissions->Delete)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

                $RoleID = $request->input("RoleID");
                $CreatedAt = date("Y-m-d H:i:s", time());

                $assigned_users = User::where("CompanyID", "=", $user->CompanyID)->where("UserRoleID", "=", $RoleID);

                if ($assigned_users->exists()) {

                    return response()->json([
                        'success' => false,
                        'error_code' => 'RELATIONAL_ENTRY_EXISTS',
                        'message' => 'This role can not be deleted! This role is already assigned',
                    ]);

                } else {

                    $UserRole = UserRole::where("id", "=", $RoleID)->where("CompanyID", "=", $user->CompanyID);

                    if (!$UserRole->exists()) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'NOT_FOUND',
                            'message' => 'The requested user role was not found!',
                        ]);
                    }


                    try {

                        if ($trash_mode === 1) {

                            $UserRole->delete();

                        } else {

                            $data = [];
                            $data["IsDeleted"] = 1;
                            $data["DeletedBy"] = $user->id;
                            $data["DeletedAt"] = $CreatedAt;
                            $UserRole->update($data);

                        }

                        return response()->json([
                            'success' => true,
                            'message' => 'User role deleted successfully!',
                        ]);

                    } catch (Exception $exception) {

                        return response()->json([
                            'success' => false,
                            'error_code' => 'DB_ERROR',
                            'message' => 'Failed to delete! ' . $exception->getMessage(),
                        ]);

                    }


                }



        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }


    /**
     * Restore a user role
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestoreUserRole(Request $request)
    {

        $user = auth()->user();
        $__module_permissions = self::ModulePermissions(self::$ModuleCodes['1']);
        if ($__module_permissions === true || (gettype($__module_permissions) === 'object' && $__module_permissions->ModuleAccess)) {

            if ($__module_permissions === true || $__module_permissions->Restore) {

                $RoleID = $request->input("RoleID");
                $CreatedAt = date("Y-m-d H:i:s", time());

                $UserRole = UserRole::where("id", "=", $RoleID)->where("CompanyID", "=", $user->CompanyID);

                if (!$UserRole->exists()) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'NOT_FOUND',
                        'message' => 'The requested user role was not found!',
                    ]);
                }


                try {

                    $data = [];
                    $data["IsDeleted"] = 0;
                    $data["UpdatedBy"] = $user->id;
                    $data["UpdatedAt"] = $CreatedAt;
                    $UserRole->update($data);

                    return response()->json([
                        'success' => true,
                        'message' => 'User role restored successfully!',
                    ]);

                } catch (Exception $exception) {

                    return response()->json([
                        'success' => false,
                        'error_code' => 'DB_ERROR',
                        'message' => 'Failed to restore! ' . $exception->getMessage(),
                    ]);

                }

            } else {

                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);

            }

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }
}
