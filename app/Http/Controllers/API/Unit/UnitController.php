<?php


namespace App\Http\Controllers\API\Unit;


use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Unit\Unit;
use App\User;
use App\Models\Bill\bill_entry_list;
use App\Models\Bill\transport_bill_list;
use \Illuminate\Http\Request;
use \Exception;

class UnitController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['8'];
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
     * Get all the unites
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetAllUnits(Request $request) {

        $search_query = (string) $request->input('search_query');
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

       try {
           $units = Unit::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('IsDeleted', '=', $trash_mode);

           if ($search_query != "") {
               $units->where("Code", "LIKE", "%" . $search_query . "%")
                   ->orWhere("Name", "LIKE", "%" . $search_query . "%");
           }

           $units = $units->orderBy('id', 'DESC')
               ->get();

           foreach ($units as $unit) {
               if ($unit->DeletedBy > 0) {
                   $deleted_user = User::where("id", "=", $unit->DeletedBy)->first();
                   $unit->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                   $unit->DeletedAt = date('d M, Y - h:ia', strtotime($unit->DeletedAt));
               }
           }


           return response()->json([
               'success' => true,
               'message' => 'Units got successfully!',
               'units' => $units,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetSingleUnit(Request $request) {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $unitID = $request->input("id");
            $unitCode = $request->input("Code");

            $unit = Unit::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('IsDeleted', '=', 0);

            if($unitID != '') {
                $unit->where('id', '=', $unitID);
            } elseif($unitCode != '') {
                $unit->where('Code', '=', $unitCode);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a unit id or code',
                ]);
            }

            if(!$unit->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Unit was not found!',
                ]);
            }

            $unit = $unit->first();

            /**
             * Flags set to boolean for javascript
             */
            $unit->IsDeleted = $unit->IsDeleted == 1;
            $unit->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Unit details got successfully!',
                'unit' => $unit,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    /**
     * Delete a unit
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeleteUnit(Request $request)
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

        $unitID = $request->input("unitID");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $existing_unit_bill_entry_list = bill_entry_list::where("unit_id", "=" , $unitID);
        $existing_unit_transport_bill_list = transport_bill_list::where("unit_id", "=" , $unitID);


        $unit = Unit::where("id", "=", $unitID)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$unit->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested unit was not found!',
            ]);
        }

        try {

            if ($trash_mode === 1) {

                if($existing_unit_bill_entry_list->exists() || $existing_unit_transport_bill_list->exists())
                {
                    return response()->json([
                        'success' => false,
                        'message' => 'This unit has already been used in another bill, unable to delete',
                    ]);
                }
                else
                {
                    $unit->delete();
                }

            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $unit->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Unit deleted successfully!',
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
     * Restore a unit
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestoreUnit(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $unitID = $request->input("unitID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $unit = Unit::where("id", "=", $unitID)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$unit->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested unit was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["UpdatedAt"] = $CreatedAt;
                $unit->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Unit restored successfully!',
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

    public function SaveUnit(Request $request) {
        $id = $request->input("id");
        $Name = $request->input("Name");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $unit = Unit::where('id', '=', $id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($unit->exists()) {

            //Update unit
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                try {
                    $unit->update([
                        'Name' => $Name,
                        'UpdatedBy' => $this->AUTH_USER->id,
                        'UpdatedAt' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Unit updated successfully!',
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

            //Insert a new unit
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    Unit::Insert([
                        'Code' => $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        'Name' => $Name,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);

                    return response()->json([
                        'success' => true,
                        'message' => 'Unit created successfully!',
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
}
