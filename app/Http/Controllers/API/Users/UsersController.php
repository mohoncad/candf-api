<?php


namespace App\Http\Controllers\API\Users;


use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\UserRole\UserRole;
use App\User;
use App\UserBranch\UserBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \Exception;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    /**
     * @var bool|mixed
     */
    private $MODULE_PERMISSIONS;
    /**
     * @var bool
     */
    private $MODULE_ACCESSIBLE;
    /**
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private $AUTH_USER;
    /**
     * @var int
     */
    private $DOC_CODE_ZEROES_LENGTH;
    /**
     * @var string
     */
    private $MODULE_CODE;
    /**
     * @var bool
     */
    private $IS_SELF_PROFILE;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->AUTH_USER = auth()->user();
            $this->DOC_CODE_ZEROES_LENGTH = 3;
            $this->MODULE_CODE = UAP::$ModuleCodes['2'];
            $this->MODULE_PERMISSIONS = UAPController::ModulePermissions(UAP::$ModuleCodes['2']);
            $this->MODULE_ACCESSIBLE = ($this->MODULE_PERMISSIONS === true || (gettype($this->MODULE_PERMISSIONS) === 'object' && $this->MODULE_PERMISSIONS->ModuleAccess));

            $this->IS_SELF_PROFILE = $request->input('id') == $this->AUTH_USER->id;

            if (!$this->MODULE_ACCESSIBLE && !$this->IS_SELF_PROFILE) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        if ($this->IS_SELF_PROFILE) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You can not access this feature while you are in self profile mode!',
            ], 403);
        }

        try {

            $trash_mode = $request->input('trash_mode');
            $trash_mode = $trash_mode === 'true' ? 1 : 0;

            if ($trash_mode === 1) {
                if (!$this->MODULE_PERMISSIONS === true || !$this->MODULE_PERMISSIONS->Trash) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'PERMISSION_DENIED',
                        'message' => 'Access Denied! You need permission to perform this action!',
                    ], 403);
                }
            }


            $status = $request->input("status");
            $user_role_id = $request->input("user_role_id");


            /**
             * By default we get all the data
             */
            $sql = "SELECT company_users.id, Code, CompanyID, ub.BranchID, IsSupportUser, IsActive, IsMasterUser, UserRoleID, FullName, email, ContactNumber, ProfilePhoto, DeletedBy, DeletedAt FROM company_users LEFT JOIN user_branches ub on company_users.id = ub.UserID WHERE CompanyID = '{$this->AUTH_USER->CompanyID}' AND (ub.BranchID = '{$this->AUTH_USER->BranchID}' OR IsMasterUser = 1) AND";

            $BasicCondition = " IsSupportUser = '0' AND IsDeleted = '{$trash_mode}'";

            if ($status != '') {
                $BasicCondition .= " AND IsActive = '{$status}'";
            }

            if ($user_role_id != '') {
                $BasicCondition .= " AND UserRoleID = '{$user_role_id}'";
            }

            $sql .= $BasicCondition;

            /**
             * By if there are any query for searching data
             * We get only the data that matches the search query
             *
             * @string $SearchQuery
             */
            $SearchQuery = trim($request->search_query);
            if ($SearchQuery != null) {

                $sql .= " AND (Code LIKE '%$SearchQuery%' OR FullName LIKE '%$SearchQuery%' OR email LIKE '%$SearchQuery%' OR ContactNumber LIKE '%$SearchQuery%')";

            }


            $PageNumber = (!$request->page || (int)$request->page <= 0) ? 1 : $request->page;
            $RecordsPerPage = $request->row_limit ?? 20;
            $Offset = $request->offset ?? 0;
            $sql .= " ORDER BY id DESC";
            $sql .= " LIMIT " . $Offset . ", " . $RecordsPerPage;

            $TotalRows = DB::select("SELECT COUNT(*) AS TotalRows FROM (" . $sql . ") as cuu");
            $TotalRows = $TotalRows[0]->TotalRows;


            $Users = DB::select($sql);

            $TotalPages = ceil($TotalRows / $RecordsPerPage);


            $Users = collect($Users);

            foreach ($Users as $User) {

                $UserRole = UserRole::where("id", "=", $User->UserRoleID);
                if ($UserRole->exists()) {
                    $User->UserRoleName = $UserRole->first()->RoleName;
                }

                if ($User->DeletedBy > 0) {
                    $deleted_user = User::where("id", "=", $User->DeletedBy)->first();
                    $User->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                    $User->DeletedAt = date('d M, Y - h:ia', strtotime($User->DeletedAt));
                }

                if ($User->ProfilePhoto != '') {
                    $User->ProfilePhoto = env('UPLOADS_CDN') . '/Users/' . $User->ProfilePhoto;
                }

            }

            $ResponseData = (object)[
                'TotalRows' => $TotalRows,
                'GridList' => $Users,
            ];


            return response()->json([
                'success' => true,
                'message' => 'User list got successfully!',
                'data' => $ResponseData,
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed! ' . $exception->getMessage(),
            ]);

        }
    }


    /**
     * Delete a user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeleteUser(Request $request)
    {
        if ($this->IS_SELF_PROFILE) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You can not access this feature while you are in self profile mode!',
            ], 403);
        }

        $UserID = $request->input("UserID");

        if ($UserID == $this->AUTH_USER->id) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You can not delete your own account!',
            ], 403);
        }

        $trash_mode = $request->input('trash_mode');
        $trash_mode = $trash_mode === 'true' ? 1 : 0;

        if ($trash_mode === 1) {
            if (!$this->MODULE_PERMISSIONS === true || !$this->MODULE_PERMISSIONS->DeleteForever) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }
        }

        if ($trash_mode === 0 && !($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Delete)) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You need permission to perform this action!',
            ], 403);
        }


        $CreatedAt = date("Y-m-d H:i:s", time());

        $User = User::where("id", "=", $UserID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID);

        if (!$User->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested user was not found!',
            ]);
        }

        if ($this->AUTH_USER->IsMasterUser == 0) {
            if ($User->first()->IsMasterUser == 1) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'I_AM_A_MASTER_USER',
                    'message' => 'You can not delete a master user',
                ]);
            }
        }

        try {

            if ($trash_mode === 1) {

                $__User = $User->first();

                $UploadsDir = "./Uploads/Users/";
                if ($__User->ProfilePhoto != null) {
                    @unlink($UploadsDir . '/' . $__User->ProfilePhoto);
                }

                $User->delete();

            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $User->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }


    /**
     * Restore a user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestoreUser(Request $request)
    {
        if ($this->IS_SELF_PROFILE) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You can not access this feature while you are in self profile mode!',
            ], 403);
        }

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $UserID = $request->input("UserID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $User = User::where("id", "=", $UserID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID);

            if (!$User->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested user was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["UpdatedAt"] = $CreatedAt;
                $User->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'User restored successfully!',
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


    public function SingleUserProfile(Request $request)
    {
        if (@$this->MODULE_PERMISSIONS === true || @$this->MODULE_PERMISSIONS->View || $this->IS_SELF_PROFILE) {

            $UserID = $request->input("id");
            $User = User::where('id', '=', $UserID)->where('CompanyID', '=', $this->AUTH_USER->CompanyID);

            if ($User->exists()) {
                $User = $User->first();


                /**
                 * Flags set to boolean for javascript
                 */
                $User->IsSelfProfile = $User->id == $this->AUTH_USER->id;
                $User->IsActive = $User->IsActive == 1;
                $User->IsDeleted = $User->IsDeleted == 1;
                $User->EmailVerified = $User->EmailVerified == 1;
                $User->IsSupportUser = $User->IsSupportUser == 1;
                $User->IsMasterUser = $User->IsMasterUser == 1;

                $User->MasterFlagEditPermission = $this->AUTH_USER->IsMasterUser == 1 && $this->AUTH_USER->id != $User->id;
                $User->HasEditPermission = ((@$this->MODULE_PERMISSIONS === true || @$this->MODULE_PERMISSIONS->Edit) && !($User->IsMasterUser && $this->AUTH_USER->IsMasterUser == 0)) || $UserID == $this->AUTH_USER->id;


                $User->UserRoleID = $User->IsMasterUser ? 0 : $User->UserRoleID;


                $BranchListArray = [];
                $UserBranchList = UserBranch::where('UserID', '=', $User->id)->get();
                foreach ($UserBranchList as $UserBranch) {
                    $title = '';

                    $BranchInfo = Branch::where('id', '=', $UserBranch->BranchID);
                    if ($BranchInfo->exists()) {
                        $title = $BranchInfo->first()->Name;
                    }

                    array_push($BranchListArray, [
                        'id' => $UserBranch->BranchID,
                        'title' => $title,
                    ]);
                }

                $User->BranchList = $BranchListArray;

                if ($User->ProfilePhoto != '') {
                    $User->ProfilePhoto = env('UPLOADS_CDN') . '/Users/' . $User->ProfilePhoto;
                }


                $User->makeHidden(['OTP_KEY']);

                return response()->json([
                    'success' => true,
                    'message' => 'User profile got successfully!',
                    'user' => $User,
                ]);
            } else {

                return response()->json([
                    'success' => false,
                    'error_code' => 'USER_NOT_FOUND',
                    'message' => 'The requested user was not found!',
                ]);

            }
        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    public function SaveUserProfile(Request $request)
    {
        $CreatedAt = date("Y-m-d H:i:s", time());
        $UserID = $request->input("id");
        $UserRoleID = $request->input("UserRoleID");
        $BranchList = $request->input("BranchList");
        $BranchList = json_decode($BranchList, true);
        $IsActive = $request->input("IsActive");
        $IsMasterUser = $request->input("IsMasterUser");
        $FullName = $request->input("FullName");
        $Email = $request->input("Email");
        $Password = $request->input("Password");
        $ContactNumber = $request->input("ContactNumber");
        $Address = $request->input("Address");

        $IsActive = $IsActive == 'true' ? 1 : 0;
        $IsMasterUser = $IsMasterUser == 'true' ? 1 : 0;
        $UserRoleID = $UserRoleID == '' ? 0 : $UserRoleID;

        if ($UserRoleID == 0 && $IsMasterUser == 0 && !($UserID == $this->AUTH_USER->id && $this->AUTH_USER->IsSupportUser == 1)) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORM_VALIDATION',
                'message' => 'You must select either a user role or check the master user!',
            ], 403);
        }


        $data = [];
        $data['CompanyID'] = $this->AUTH_USER->CompanyID;
        $data['IsActive'] = $IsActive;
        $data['UserRoleID'] = $UserRoleID;
        $data['EmailVerified'] = 1;
        $data['FullName'] = $FullName;
        $data['email'] = $Email;
        $data['ContactNumber'] = $ContactNumber;
        $data['Address'] = $Address;

        if ($Password != '') {
            $data['password'] = Hash::make($Password);
        }

        if ($this->AUTH_USER->IsMasterUser == 1) {
            $data['UserRoleID'] = $IsMasterUser == 1 ? 1 : $UserRoleID; //User role id is 1 when he is selected as a master user
            $data['IsMasterUser'] = $IsMasterUser;
        } else if ($IsMasterUser == 1) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You can not add or update a master user profile!',
            ], 403);
        }


        $User = User::where('id', '=', $UserID)->where('CompanyID', '=', $this->AUTH_USER->CompanyID);

        if ($User->exists()) {

            //self status change check
            if ($IsActive == 0 && $this->AUTH_USER->id == $UserID) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access denied! You can not ban your own account!',
                ], 403);
            }

            //Check Email Address Existence
            $EmailCheck = User::where('id', '!=', $UserID)->where('email', '=', $Email);
            if ($EmailCheck->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'EMAIL_EXISTS',
                    'message' => 'This email was registered before! Please choose another one!',
                ]);
            }

            //Update the user
            if (@$this->MODULE_PERMISSIONS === true || @$this->MODULE_PERMISSIONS->Edit || $this->AUTH_USER->id == $UserID) {


                //Upload the profile photo

                $UploadsDir = "./Uploads/Users/";
                $NewFileName = null;
                if (isset($_FILES["ProfilePhoto"]) && $_FILES["ProfilePhoto"]["name"] != null) {
                    $uploadOk = 1;
                    $target_dir = $UploadsDir;
                    $target_file = $target_dir . basename($_FILES["ProfilePhoto"]["name"]);
                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    $check = getimagesize($_FILES["ProfilePhoto"]["tmp_name"]);
                    if ($check !== false) {
                        $uploadOk = 1;
                    } else {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'FILE_IS_NOT_AN_IMAGE',
                            'message' => 'The selected file is not an image!',
                        ]);
                    }
                    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
                        $uploadOk = 0;

                        return response()->json([
                            'success' => false,
                            'error_code' => 'FILE_FORMAT_ERROR',
                            'message' => 'Sorry, only JPG, JPEG & PNG files are allowed!',
                        ]);
                    } else {
                        $temp = explode(".", $_FILES["ProfilePhoto"]["name"]);
                        $NewFileName = 'C_AND_F_DOC__' . strtoupper(md5(time())) . '__' . round(microtime(true)) . '.' . end($temp);
                        if (move_uploaded_file($_FILES["ProfilePhoto"]["tmp_name"], $target_dir . $NewFileName)) {

                            //do anything

                        } else {
                            $NewFileName = null;
                            return response()->json([
                                'success' => false,
                                'error_code' => 'UPLOAD_ERROR',
                                'message' => 'The profile photo could not be uploaded!',
                            ]);
                        }
                    }
                }


                if ($NewFileName != null) {
                    $__user = $User->first();
                    if ($__user->ProfilePhoto != null) {
                        @unlink($UploadsDir . '/' . $__user->ProfilePhoto);
                    }
                    //update the new logo
                    $data['ProfilePhoto'] = $NewFileName;
                }


                $data['UpdatedBy'] = $this->AUTH_USER->id;
                $data['UpdatedAt'] = $CreatedAt;

                $User->update($data);


                //Delete all the branches assigned before
                UserBranch::where('UserID', '=', $UserID)->delete();

                foreach ($BranchList as $Branch) {
                    UserBranch::Insert([
                        'UserID' => $UserID,
                        'BranchID' => $Branch['id'],
                    ]);
                }


                return response()->json([
                    'success' => true,
                    'message' => 'User Profile Updated!',
                ]);

            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

        } else {

            //Add a new user
            if (@$this->MODULE_PERMISSIONS === true || @$this->MODULE_PERMISSIONS->Add || $this->IS_SELF_PROFILE) {

                //Check Email Address Existence
                $EmailCheck = User::where('email', '=', $Email);
                if ($EmailCheck->exists()) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'EMAIL_EXISTS',
                        'message' => 'This email was registered before! Please choose another one!',
                    ]);
                }


                $UploadsDir = "./Uploads/Users/";
                $NewFileName = null;
                if (isset($_FILES["ProfilePhoto"]) && $_FILES["ProfilePhoto"]["name"] != null) {
                    $uploadOk = 1;
                    $target_dir = $UploadsDir;
                    $target_file = $target_dir . basename($_FILES["ProfilePhoto"]["name"]);
                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    $check = getimagesize($_FILES["ProfilePhoto"]["tmp_name"]);
                    if ($check !== false) {
                        $uploadOk = 1;
                    } else {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'FILE_IS_NOT_AN_IMAGE',
                            'message' => 'The selected file is not an image!',
                        ]);
                    }
                    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
                        $uploadOk = 0;

                        return response()->json([
                            'success' => false,
                            'error_code' => 'FILE_FORMAT_ERROR',
                            'message' => 'Sorry, only JPG, JPEG & PNG files are allowed!',
                        ]);
                    } else {
                        $temp = explode(".", $_FILES["ProfilePhoto"]["name"]);
                        $NewFileName = 'C_AND_F_DOC__' . strtoupper(md5(time())) . '__' . round(microtime(true)) . '.' . end($temp);
                        if (move_uploaded_file($_FILES["ProfilePhoto"]["tmp_name"], $target_dir . $NewFileName)) {

                            //do anything

                        } else {
                            $NewFileName = null;
                            return response()->json([
                                'success' => false,
                                'error_code' => 'UPLOAD_ERROR',
                                'message' => 'The profile photo could not be uploaded!',
                            ]);
                        }
                    }
                }


                if ($NewFileName != null) {
                    //update the new logo
                    $data['ProfilePhoto'] = $NewFileName;
                }

                $SysCode = new SysCodeController();

                $data['Code'] = $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH);
                $data['CompanyID'] = $this->AUTH_USER->CompanyID;
                $data['CreatedBy'] = $this->AUTH_USER->id;
                $data['CreatedAt'] = $CreatedAt;

                $UserID = User::insertGetId($data);

                $SysCode->UpdateIncrement($this->MODULE_CODE);

                //Insert the branches
                foreach ($BranchList as $Branch) {
                    UserBranch::Insert([
                        'UserID' => $UserID,
                        'BranchID' => $Branch['id'],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'User added successfully!',
                ]);

            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

        }
    }
}
