<?php


namespace App\Http\Controllers\API\UAP;


use App\Models\UserRole\UserRole;
use Illuminate\Http\Request;
use \Exception;

class FreeUserRoleListController extends \App\Http\Controllers\Controller
{

    /**
     * Freely Get all the user roles
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetUserRolesFreely(Request $request)
    {
        $user = auth()->user();

        try {
            $UserRoleList = UserRole::where("CompanyID", "=", $user->CompanyID)->where("IsActive", "=", 1)->where("IsDeleted", "=", 0)->orderBy('id', 'DESC');


            if ($UserRoleList->exists()) {
                $UserRoleList = $UserRoleList->get();
                foreach ($UserRoleList as $UserRole) {
                    $UserRole->title = $UserRole->RoleName;
                }
            } else {
                $UserRoleList = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free User role List Got Successfully!',
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
}
