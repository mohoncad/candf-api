<?php


namespace App\Http\Controllers\API\Supplier;


use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Supplier\Supplier;
use App\User;
use \Illuminate\Http\Request;
use \Exception;
use DB;

class SupplierController extends Controller
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
        $this->AUTH_USER = auth()->user();
        $this->middleware(function ($request, $next) {
            $this->AUTH_USER = auth()->user();
            $this->DOC_CODE_ZEROES_LENGTH = 5;
            $this->MODULE_CODE = UAP::$ModuleCodes['10'];
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
     * Get all the suppliers
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetAllSuppliers(Request $request) {

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
           $SupplierList = Supplier::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            //    ->where('BranchID', '=', $this->AUTH_USER->BranchID)
               ->where('IsDeleted', '=', $trash_mode);

        //$SupplierList = Supplier::all();

           if ($search_query != "") {
               $SupplierList->where("Code", "LIKE", "%" . $search_query . "%")
                   ->orWhere("Name", "LIKE", "%" . $search_query . "%")
                   ->orWhere("Phone", "LIKE", "%" . $search_query . "%")
                   ->orWhere("Email", "LIKE", "%" . $search_query . "%")
                   ->orWhere("Status", "LIKE", "%" . $search_query . "%")
                   ->orWhere("CreatedAt", "LIKE", "%" . $search_query . "%");
           }

           $total_bill = $SupplierList->get();

           $SupplierList = $SupplierList->orderBy('id', 'DESC')
            ->when($search_query != "", function($query) use ($skip){
                return $query->skip($skip);
            })
           ->take($row_limit)
           ->get();


           foreach ($SupplierList as $Supplier) {
               if ($Supplier->DeletedBy > 0) {
                   $deleted_user = User::where("id", "=", $Supplier->DeletedBy)->first();
                   $Supplier->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                   $Supplier->DeletedAt = date('d M, Y - h:ia', strtotime($Supplier->DeletedAt));
               }
           }


           return response()->json([
               'success' => true,
               'message' => 'Supplier List got successfully!',
               'auth_user_company_id' => $this->AUTH_USER->CompanyID,
               'auth_user_branch_id' => $this->AUTH_USER->BranchID,
               'auth_user_is_deleted' => $trash_mode,
               'supplier_list' => $SupplierList,
               'total_rows' => $total_bill->count(),
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetSingleSupplier(Request $request) {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $SupplierID = $request->input("id");
            $SupplierCode = $request->input("Code");

            $Supplier = Supplier::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('IsDeleted', '=', 0);

            if($SupplierID != '') {
                $Supplier->where('id', '=', $SupplierID);
            } elseif($SupplierCode != '') {
                $Supplier->where('Code', '=', $SupplierCode);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a Supplier id or code',
                ]);
            }

            if(!$Supplier->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Supplier was not found!',
                ]);
            }

            $Supplier = $Supplier->first();

            /**
             * Flags set to boolean for javascript
             */
            $Supplier->IsDeleted = $Supplier->IsDeleted == 1;
            $Supplier->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Supplier details got successfully!',
                'supplier' => $Supplier,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    /**
     * Delete a supplier
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeleteSupplier(Request $request)
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

        $SupplierID = $request->input("SupplierID");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $Supplier = Supplier::where("id", "=", $SupplierID)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$Supplier->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested supplier was not found!',
                'auth_user_id' => $this->AUTH_USER->CompanyID,
                'auth_branch_id' => $this->AUTH_USER->BranchID
            ]);
        }

        try {

            if ($trash_mode === 1) {

                $Supplier->delete();

            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $Supplier->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Supplier deleted successfully!',
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
     * Restore a supplier
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestoreSupplier(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $SupplierID = $request->input("SupplierID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $Supplier = Supplier::where("id", "=", $SupplierID)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$Supplier->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested supplier was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["UpdatedAt"] = $CreatedAt;
                $Supplier->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Supplier restored successfully!',
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

    public function SaveSupplier(Request $request) {
        $id = $request->input("id");
        $Name = $request->input("Name");
        $Phone = $request->input("Phone");
        $Email = $request->input("Email");
        $Address = $request->input("Address");
        $Status = $request->input("Status");
        $CreatedAt = date("Y-m-d H:i:s", time());
        $Code = $request->input("Code");

        $Supplier = Supplier::where('id', '=', $id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($Supplier->exists()) {

            //Update supplier
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                try {
                    $Supplier->update([
                        'Name' => $Name,
                        'Phone' => $Phone,
                        'Email' => $Email,
                        'Address' => $Address,
                        'Status' => isset($Status) ? $Status : 0,
                        'UpdatedBy' => $this->AUTH_USER->id,
                        'UpdatedAt' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Supplier updated successfully!',
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

            //Insert a new supplier
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    Supplier::Insert([
                        'Code' => $Code,
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        'Name' => $Name,
                        'Phone' => $Phone,
                        'Email' => $Email,
                        'Address' => $Address,
                        'Status' => isset($Status) ? $Status : 0,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);

                    return response()->json([
                        'success' => true,
                        'message' => 'Supplier created successfully!',
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
        $code = "SUP" . $this->NewDocCode();

        return response()->json([
            'success' => true,
            'supplier_newCode' => $code,
        ]);
    }
}
