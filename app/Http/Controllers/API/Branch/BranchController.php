<?php


namespace App\Http\Controllers\API\Branch;


use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\User;
use App\UserBranch\UserBranch;
use \Illuminate\Http\Request;
use \Exception;

class BranchController extends Controller
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
     * @var string
     */
    private $MODULE_CODE;
    /**
     * @var int
     */
    private $DOC_CODE_ZEROES_LENGTH;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->AUTH_USER = auth()->user();
            $this->DOC_CODE_ZEROES_LENGTH = 3;
            $this->MODULE_CODE = UAP::$ModuleCodes['3'];
            $this->MODULE_PERMISSIONS = UAPController::ModulePermissions($this->MODULE_CODE);
            $this->MODULE_ACCESSIBLE = ($this->MODULE_PERMISSIONS === true || (gettype($this->MODULE_PERMISSIONS) === 'object' && $this->MODULE_PERMISSIONS->ModuleAccess));

            if (!$this->MODULE_ACCESSIBLE) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

            return $next($request);
        });
    }

    public function NewDocCode() {
        $SysCodeC = new SysCodeController();
        return $SysCodeC->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH);
    }

    /**
     * Get all the branches
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetAllBranches(Request $request) {

        $search_query = (string) $request->input('search_query');
        $trash_mode = $request->input('trash_mode');
        $trash_mode = $trash_mode === 'true' ? 1 : 0;
        $row_limit = $request->row_limit ?? 20;
        $skip = $request->offset ?? 0;

        if ($trash_mode === 1) {
            if (!$this->MODULE_PERMISSIONS === true || !$this->MODULE_PERMISSIONS->Trash) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }
        }

       try {
           $BranchList = Branch::where('CompanyID', '=', $this->AUTH_USER->CompanyID)->where('IsDeleted', '=', $trash_mode);

           if ($search_query != "") {
               $BranchList = $BranchList->where("Code", "LIKE", "%" . $search_query . "%")->orWhere("Name", "LIKE", "%" . $search_query . "%")->orWhere("Email", "LIKE", "%" . $search_query . "%")->orWhere("ContactNumber", "LIKE", "%" . $search_query . "%");
           }

           $BranchList = $BranchList->orderBy('id', 'DESC');

           $total_bill = $BranchList->get();

           $BranchList = $BranchList
                        ->when($search_query != "", function($query) use ($skip){
                            return $query->skip($skip);
                        })
                        ->take($row_limit) 
                        ->get();

           foreach ($BranchList as $Branch) {
               if ($Branch->DeletedBy > 0) {
                   $deleted_user = User::where("id", "=", $Branch->DeletedBy)->first();
                   $Branch->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                   $Branch->DeletedAt = date('d M, Y - h:ia', strtotime($Branch->DeletedAt));
               }
           }


           return response()->json([
               'success' => true,
               'message' => 'Branch List got successfully!',
               'branch_list' => $BranchList,
               'total_rows' => $total_bill->count()
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetSingleBranch(Request $request) {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $BranchID = $request->input("id");
            $BranchCode = $request->input("Code");

            $Branch = Branch::where('CompanyID', '=', $this->AUTH_USER->CompanyID)->where('IsDeleted', '=', 0);

            if($BranchID != '') {
                $Branch = $Branch->where('id', '=', $BranchID);
            } elseif($BranchCode != '') {
                $Branch = $Branch->where('Code', '=', $BranchCode);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a branch id or code',
                ]);
            }

            if(!$Branch->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Branch was not found!',
                ]);
            }

            $Branch = $Branch->first();

            /**
             * Flags set to boolean for javascript
             */
            $Branch->IsDeleted = $Branch->IsDeleted == 1;
            $Branch->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Branch details got successfully!',
                'branch' => $Branch,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    /**
     * Delete a branch
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeleteBranch(Request $request)
    {

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

        $BranchID = $request->input("BranchID");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $Branch = Branch::where("id", "=", $BranchID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID);

        if (!$Branch->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested branch was not found!',
            ]);
        }

        try {

            if ($trash_mode === 1) {

                $Branch->delete();

            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $Branch->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully!',
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
     * Restore a branch
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestoreBranch(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $BranchID = $request->input("BranchID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $Branch = Branch::where("id", "=", $BranchID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID);

            if (!$Branch->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested branch was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["UpdatedAt"] = $CreatedAt;
                $Branch->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Branch restored successfully!',
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

    public function SaveBranch(Request $request) {
        $id = $request->input("id");
        $Name = $request->input("Name");
        $Address = $request->input("Address");
        $Email = $request->input("Email");
        $ContactNumber = $request->input("ContactNumber");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $Branch = Branch::where('id', '=', $id)->where('CompanyID', '=', $this->AUTH_USER->CompanyID);

        if($Branch->exists()) {

            //Update branch
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                try {
                    $Branch->update([
                        'Name' => $Name,
                        'Email' => $Email,
                        'ContactNumber' => $ContactNumber,
                        'Address' => $Address,
                        'UpdatedBy' => $this->AUTH_USER->id,
                        'UpdatedAt' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Branch updated successfully!',
                    ]);
                } catch (Exception $exception) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'DB_ERROR',
                        'message' => 'Failed to update! ' . $exception->getMessage(),
                    ]);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

        } else {

            //Insert a new branch
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    Branch::Insert([
                        'Code' => $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'Name' => $Name,
                        'Email' => $Email,
                        'ContactNumber' => $ContactNumber,
                        'Address' => $Address,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);

                    return response()->json([
                        'success' => true,
                        'message' => 'Branch created successfully!',
                    ]);
                } catch (Exception $exception) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'DB_ERROR',
                        'message' => 'Failed to create! ' . $exception->getMessage(),
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
    }

    public function GetUpdatedCode(Request $request)
    {
        $code = $this->NewDocCode();

        return response()->json([
            'success' => true,
            'branch_newCode' => $code,
        ]);
    }
}
