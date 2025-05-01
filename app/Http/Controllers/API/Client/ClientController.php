<?php


namespace App\Http\Controllers\API\Client;


use App\Helper\Helper;
use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use App\User;
use \Illuminate\Http\Request;
use \Exception;
use Validator;
use Illuminate\Support\Facades\Storage;
use DB;

class ClientController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['9'];
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


    public function NewDocCode()
    {
        $SysCodeC = new SysCodeController();
        return $SysCodeC->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH);
    }

    /**
     * Get all the clients
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetAllClients(Request $request)
    {

        $search_query = (string)$request->input('search_query');
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
            $ClientList = Client::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                // ->where('BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('IsDeleted', '=', $trash_mode);

            if ($search_query != "") {
                $ClientList = $ClientList->where("Code", "LIKE", "%" . $search_query . "%")
                    ->orWhere("Name", "LIKE", "%" . $search_query . "%")
                    ->orWhere("CodePrefix", "LIKE", "%" . $search_query . "%")
                    ->orWhere("Mobile", "LIKE", "%" . $search_query . "%")
                    ->orWhere("CreatedAt", "LIKE", "%" . $search_query . "%");
                //group id remaining
            }

            $total_bill = $ClientList->get();

            $ClientList = $ClientList->orderBy('id', 'DESC')
                ->when($search_query != "", function ($query) use ($skip) {
                    return $query->skip($skip);
                })
                ->take($row_limit)
                ->get();

            foreach ($ClientList as $Client) {
                if ($Client->DeletedBy > 0) {
                    $deleted_user = User::where("id", "=", $Client->DeletedBy)->first();
                    $Client->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                    $Client->DeletedAt = date('d M, Y - h:ia', strtotime($Client->DeletedAt));
                }
            }


            return response()->json([
                'success' => true,
                'message' => 'Client List got successfully!',
                'client_list' => $ClientList,
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

    public function GetSingleClient(Request $request)
    {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $ClientID = $request->input("id");
            $ClientCode = $request->input("Code");

            $Client = Client::where('CompanyID', '=', $this->AUTH_USER->CompanyID)->where('BranchID', '=', $this->AUTH_USER->BranchID)->where('IsDeleted', '=', 0);

            if ($ClientID != '') {
                $Client->where('id', '=', $ClientID);
            } elseif ($ClientCode != '') {
                $Client->where('Code', '=', $ClientCode);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a Client id or code',
                ]);
            }

            if (!$Client->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Client was not found!',
                ]);
            }

            $Client = $Client->first();
            if($Client->VATRegCopy!=null)
            {
                $Client->VATRegCopy = config('url_config.app_url') . '/client/' . $Client->VATRegCopy;
            }

            if($Client->Quotation!=null)
            {
                $Client->Quotation = config('url_config.app_url') . '/client/' . $Client->Quotation;
            }
                      
            /**
             * Flags set to boolean for javascript
             */
            $Client->IsDeleted = $Client->IsDeleted == 1;
            $Client->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Client detail got successfully!',
                'client' => $Client,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    /**
     * Delete a client
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeleteClient(Request $request)
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

        $ClientID = $request->input("ClientID");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $Client = Client::where("id", "=", $ClientID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID)->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$Client->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested Client was not found!',
            ]);
        }

        try {

            if ($trash_mode === 1) {

                $Client->delete();

            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $Client->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully!',
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
     * Restore a client
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestoreClient(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $ClientID = $request->input("ClientID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $Client = Client::where("id", "=", $ClientID)->where("CompanyID", "=", $this->AUTH_USER->CompanyID)->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$Client->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested Client was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["UpdatedAt"] = $CreatedAt;
                $Client->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Client restored successfully!',
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

    public function SaveClient(Request $request)
    {

        // $VATRegCopyFilename = Helper::upload($request, 'VATRegCopy', 'public/client/');
        $VATRegCopyFilename = Helper::upload($request, 'VATRegCopy', 'client/');
        $QuotationFileName = Helper::upload($request, 'Quotation', 'client/');



        $id = $request->input("id");
        $Name = $request->input("Name");
        $MailingAddess = $request->input("MailingAddess") ?? '';
        $Phone = $request->input("Phone");
        $Email = $request->input("Email");
        $ClientGroupID = $request->input("ClientGroupID");
        $CodePrefix = $request->input("CodePrefix");
        $Fax = $request->input("Fax");
        $Mobile = $request->input("Mobile");
        $Web = $request->input("Web");
        $IRC = $request->input("IRC");
        $BIN_VAT = $request->input("BIN_VAT");
        $BondLicense = $request->input("BondLicense");
        $GenBond = $request->input("GenBond");
        $ERC = $request->input("ERC");
        $TIN = $request->input("TIN");
        $BOIReg = $request->input("BOIReg");
        $Note = $request->input("Note");
        $CreatedAt = date("Y-m-d H:i:s", time());
        $Code = $request->input("Code");

        $Client = Client::where('id', '=', $id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if ($Client->exists()) {

            //Update Client
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                try {
                    $Client->update([
                        'Name' => $Name,
                        'MailingAddess' => $MailingAddess,
                        'Phone' => $Phone,
                        'Email' => $Email,
                        'ClientGroupID' => $ClientGroupID,
                        'CodePrefix' => $CodePrefix,
                        'Fax' => $Fax,
                        'Mobile' => $Mobile,
                        'Web' => $Web,
                        'IRC' => $IRC,
                        'BIN_VAT' => $BIN_VAT,
                        'BondLicense' => $BondLicense,
                        'GenBond' => $GenBond,
                        'ERC' => $ERC,
                        'TIN' => $TIN,
                        'BOIReg' => $BOIReg,
                        'Note' => $Note,
                        'VATRegCopy' => $VATRegCopyFilename,
                        'Quotation' => $QuotationFileName,
                        'UpdatedBy' => $this->AUTH_USER->id,
                        'UpdatedAt' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Client updated successfully!',
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

            //Insert a new Client
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    Client::Insert([
                        'Code' => $Code,
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        'Name' => $Name,
                        'MailingAddess' => $MailingAddess,
                        'Phone' => $Phone,
                        'Email' => $Email,
                        'ClientGroupID' => $ClientGroupID,
                        'CodePrefix' => $CodePrefix,
                        'Fax' => $Fax,
                        'Mobile' => $Mobile,
                        'Web' => $Web,
                        'IRC' => $IRC,
                        'BIN_VAT' => $BIN_VAT,
                        'BondLicense' => $BondLicense,
                        'GenBond' => $GenBond,
                        'ERC' => $ERC,
                        'TIN' => $TIN,
                        'BOIReg' => $BOIReg,
                        'Note' => $Note,
                        'VATRegCopy' => $VATRegCopyFilename,
                        'Quotation' => $QuotationFileName,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);

                    return response()->json([
                        'success' => true,
                        'message' => 'Client created successfully!',
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
        $code = "CT" . $this->NewDocCode();

        return response()->json([
            'success' => true,
            'client_newCode' => $code,
        ]);
    }
}
