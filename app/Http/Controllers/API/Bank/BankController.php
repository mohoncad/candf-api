<?php


namespace App\Http\Controllers\API\Bank;


use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Bank\Bank;
use App\Models\Bill\bill_entry_list;
use App\User;
use \Illuminate\Http\Request;
use \Exception;

class BankController extends Controller
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
            $this->DOC_CODE_ZEROES_LENGTH = 3;
            $this->MODULE_CODE = UAP::$ModuleCodes['4'];
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
     * Get all the bankes
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetAllBanks(Request $request) {

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
           $BankList = Bank::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('IsDeleted', '=', $trash_mode);

           if ($search_query != "") {
               $BankList = $BankList->where("Code", "LIKE", "%" . $search_query . "%")
                   ->orWhere("Name", "LIKE", "%" . $search_query . "%")
                   ->orWhere("BranchName", "LIKE", "%" . $search_query . "%")
                   ->orWhere("AccountType", "LIKE", "%" . $search_query . "%")
                   ->orWhere("AccountName", "LIKE", "%" . $search_query . "%")
                   ->orWhere("AccountNumber", "LIKE", "%" . $search_query . "%");
           }

           $BankList = $BankList->orderBy('id', 'DESC');

           $BankList = $BankList->get();

           foreach ($BankList as $Bank) {
               if ($Bank->DeletedBy > 0) {
                   $deleted_user = User::where("id", "=", $Bank->DeletedBy)->first();
                   $Bank->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                   $Bank->DeletedAt = date('d M, Y - h:ia', strtotime($Bank->DeletedAt));
               }
           }


           return response()->json([
               'success' => true,
               'message' => 'Bank List got successfully!',
               'bank_list' => $BankList,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetSingleBank(Request $request) {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $BankID = $request->input("id");
            $BankCode = $request->input("Code");

            $Bank = Bank::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('IsDeleted', '=', 0);

            if($BankID != '') {
                $Bank = $Bank->where('id', '=', $BankID);
            } elseif($BankCode != '') {
                $Bank = $Bank->where('Code', '=', $BankCode);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a bank id or code',
                ]);
            }

            if(!$Bank->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Bank was not found!',
                ]);
            }

            $Bank = $Bank->first();

            /**
             * Flags set to boolean for javascript
             */
            $Bank->IsDeleted = $Bank->IsDeleted == 1;
            $Bank->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Bank details got successfully!',
                'bank' => $Bank,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    /**
     * Delete a bank
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeleteBank(Request $request)
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

        $BankID = $request->input("BankID");
        $CreatedAt = date("Y-m-d H:i:s", time());

        //checking whether this bank ID has already been used in any other bill or not
        $existing_bank = bill_entry_list::where("client_bank_name", "=" , $BankID);

        $Bank = Bank::where("id", "=", $BankID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID)->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$Bank->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested bank was not found!',
            ]);
        }

        try {

            if ($trash_mode === 1) {

                if($existing_bank->exists())
                {
                    return response()->json([
                        'success' => false,
                        'message' => 'This bank has already been used in another bill, unable to delete',
                    ]);
                }
                else
                {
                    $Bank->delete();
                }

            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $Bank->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Bank deleted successfully!',
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
     * Restore a bank
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestoreBank(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $BankID = $request->input("BankID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $Bank = Bank::where("id", "=", $BankID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID)->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$Bank->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested bank was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["UpdatedAt"] = $CreatedAt;
                $Bank->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Bank restored successfully!',
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

    public function SaveBank(Request $request) {
        $id = $request->input("id");
        $Name = $request->input("Name");
        $BranchName = $request->input("BranchName");
        $AccountType = $request->input("AccountType");
        $AccountName = $request->input("AccountName");
        $AccountNumber = $request->input("AccountNumber");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $Bank = Bank::where('id', '=', $id)->where('CompanyID', '=', $this->AUTH_USER->CompanyID)->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($Bank->exists()) {

            //Update bank
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                try {
                    $Bank->update([
                        'Name' => $Name,
                        'BranchName' => $BranchName,
                        'AccountType' => $AccountType,
                        'AccountName' => $AccountName,
                        'AccountNumber' => $AccountNumber,
                        'UpdatedBy' => $this->AUTH_USER->id,
                        'UpdatedAt' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Bank updated successfully!',
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

            //Insert a new bank
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    Bank::Insert([
                        'Code' => $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        'Name' => $Name,
                        'BranchName' => $BranchName,
                        'AccountType' => $AccountType,
                        'AccountName' => $AccountName,
                        'AccountNumber' => $AccountNumber,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);

                    return response()->json([
                        'success' => true,
                        'message' => 'Bank created successfully!',
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
