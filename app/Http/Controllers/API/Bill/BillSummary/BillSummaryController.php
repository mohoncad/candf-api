<?php

namespace App\Http\Controllers\API\Bill\BillSummary;

use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Models\Bill\bill_summary_list;
use App\Models\Bill\bill_entry_list;
use App\Models\Bill\transport_bill_list;
use App\Models\Client\Client;
use App\Models\Company\Company;
use App\User;
use Illuminate\Http\Request;
use Validator,Redirect,Response,File;
use DateTime;
use \Exception;
use Illuminate\Support\Facades\Log;
use SebastianBergmann\Environment\Console;

class BillSummaryController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['14'];
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

    public function NewDocCode($client_id,$bill_type)
    {
        $company = Company::find($this->AUTH_USER->CompanyID);
        $SysCodeC = new SysCodeController();
        
        if($company->InvoiceNumberType == "generic")
        {
            return $SysCodeC->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH);
        }
        else
        {
            return $SysCodeC->getNewBillInvoiceNumber($client_id, $SysCodeC::client,$this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH, $bill_type);
        }
    }

    public function GetAllBillSummary(Request $request)
    {

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
           $bill_summary_list = bill_summary_list::where('bill_summary_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('bill_summary_lists.BranchID', '=', $this->AUTH_USER->BranchID)
               ->where('bill_summary_lists.IsDeleted', '=', $trash_mode)
               ->leftJoin('clients', 'bill_summary_lists.client_id', '=', 'clients.id');

           if ($search_query != "") {
               $bill_summary_list->where("bill_summary_lists.summary_code", "LIKE", "%" . $search_query . "%")
                                ->orWhere("bill_summary_lists.CreatedAt", "LIKE", "%" . $search_query . "%")
                                ->orWhere("bill_summary_lists.bill_type", "LIKE", "%" . $search_query . "%")
                                ->orWhere("clients.Name", "LIKE", "%" . $search_query . "%");
                                }

            $total_bill = $bill_summary_list->get();

            $bill_summary_list = $bill_summary_list->select('bill_summary_lists.*','clients.Name as client_name')
            ->orderBy('bill_summary_lists.summary_id', 'DESC')
            // ->skip($skip)
            ->when(empty($search_query), function($query) use ($skip){
                return $query->skip($skip);
            })
            ->take($row_limit)
            ->get();


           return response()->json([
               'success' => true,
               'message' => 'bill summary List got successfully!',
               'bill_summary_list' => $bill_summary_list,
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

    public function GetBillList(Request $request)
    {
        $client_id = $request->input('client_id');
        $bill_type = $request->input('bill_type');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $bill_summary_id = $request->input('bill_summary_id') ?? null;

        $from_date = date_create_from_format("d/m/Y", $from_date)->format("Y-m-d");
        if($to_date != null)
        {
            $to_date = date_create_from_format("d/m/Y", $to_date)->format("Y-m-d");
        }
        // echo $from_date;


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
           if($bill_type == "Import" || $bill_type == "Export")
           {
               if($to_date == null)
               {
                    $bill_summary_list = bill_entry_list::where('bill_entry_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                    ->where('bill_entry_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                    ->where('bill_entry_lists.IsDeleted', '=', $trash_mode)
                    ->where('bill_entry_lists.bill_type', '=', $bill_type)
                    ->where('bill_entry_lists.bill_date', '>=', $from_date)
                    ->where('bill_entry_lists.client_id', '=', $client_id)
                    ->where('bill_entry_lists.full_paid', '=', 0);
    
                    $bill_summary_list = $bill_summary_list->select('bill_entry_lists.*')
                    ->orderBy('bill_entry_lists.id')->get();
               }
               else
               {
                    $bill_summary_list = bill_entry_list::where('bill_entry_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                    ->where('bill_entry_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                    ->where('bill_entry_lists.IsDeleted', '=', $trash_mode)
                    ->where('bill_entry_lists.bill_type', '=', $bill_type)
                    ->where('bill_entry_lists.bill_date', '>=', $from_date)
                    ->where('bill_entry_lists.bill_date', '<=', $to_date)
                    ->where('bill_entry_lists.client_id', '=', $client_id)
                    ->where('bill_entry_lists.full_paid', '=', 0);

                    $bill_summary_list = $bill_summary_list->select('bill_entry_lists.*')
                    ->orderBy('bill_entry_lists.id')->get();
               }
           }

           if($bill_type == "Transport")
           {
                if($to_date == "")
                {
                    $bill_summary_list = transport_bill_list::where('transport_bill_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                    ->where('transport_bill_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                    ->where('transport_bill_lists.IsDeleted', '=', $trash_mode)
                    ->where('transport_bill_lists.bill_date', '>=', $from_date)
                    ->where('transport_bill_lists.client_id', '=', $client_id)
                    ->where('transport_bill_lists.full_paid', '=', 0);
    
                    $bill_summary_list = $bill_summary_list->select('transport_bill_lists.bill_id as id','transport_bill_lists.*')
                    ->orderBy('transport_bill_lists.bill_id')->get();
                }
                else if($to_date != "")
                {
                    $bill_summary_list = transport_bill_list::where('transport_bill_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                    ->where('transport_bill_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                    ->where('transport_bill_lists.IsDeleted', '=', $trash_mode)
                    // ->whereBetween('transport_bill_lists.bill_date', [$from_date,$to_date])
                    // ->where('transport_bill_lists.bill_date', '<=', $to_date)
                    ->where('transport_bill_lists.client_id', '=', $client_id)
                    ->where('transport_bill_lists.full_paid', '=', 0);

                    $bill_summary_list->whereBetween('transport_bill_lists.bill_date', [$from_date,$to_date]); 
    
                    $bill_summary_list = $bill_summary_list->select('transport_bill_lists.bill_id as id','transport_bill_lists.*')
                    ->orderBy('transport_bill_lists.bill_id')->get();
                }
           }

           $existing_bill_summary_list = bill_summary_list::where('bill_summary_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('bill_summary_lists.BranchID', '=', $this->AUTH_USER->BranchID)
               ->where('bill_summary_lists.IsDeleted', '=', $trash_mode)
               ->where('bill_summary_lists.client_id','=',$client_id)
               ->where('bill_summary_lists.bill_type', '=', $bill_type)
               ->when($bill_summary_id != null, function($query) use($bill_summary_id){
                   return $query->where('bill_summary_lists.summary_id', '!=', $bill_summary_id);
               });


                
               $existing_bill_summary_list = $existing_bill_summary_list->pluck('bill_summary_lists.InvoiceList')->toArray();
               if(count($existing_bill_summary_list) > 0) {
                   $existing_bill_summary_list = call_user_func_array('array_merge', array_map("json_decode" ,$existing_bill_summary_list));
    
                   foreach($bill_summary_list as $bx)
                   {
                       $bx->valid = (in_array($bx->id, $existing_bill_summary_list)) ? 0 : 1;
                   }
               } else {
                $existing_bill_summary_list = [];
               }

                        
                       
           return response()->json([
               'success' => true,
               'message' => 'bill summary List got successfully!',
               'bill_summary_list' => $bill_summary_list,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }


    public function SaveBillSummary(Request $request)
    {
        $invoiceList = json_encode($request->input("invoiceList"));
        //echo $invoiceList;

        $summary_id = $request->input("summary_id");
        $user_id = $this->AUTH_USER->id;
        $summary_code = $request->input("summary_code");
        $subject = $request->input("subject");
        $bill_type = $request->input("bill_type");
        $client_id = $request->input("client_id");
        $from_date = $request->input("from_date");
        $to_date = $request->input("to_date") ?: "";
        $total_due = $request->input("total_due");
        $summary_timestamp = time();
        $summary_dd = date('d', $summary_timestamp);
        $summary_mm = date('m', $summary_timestamp);
        $summary_yyyy = date('y', $summary_timestamp);
        $summary_date = $summary_dd . '/' . $summary_mm . '/' . $summary_yyyy;
        $CreatedAt = date("Y-m-d H:i:s", time());

        $save_bill_summary = bill_summary_list::where('summary_id', '=', $summary_id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($save_bill_summary->exists()) {

            //Update summary_bill_list
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {               
                try {
                    $save_bill_summary->update([
                        'user_id' => $user_id,
                        'summary_code' => $summary_code,
                        'bill_type' => $bill_type,
                        'client_id' => $client_id,
                        'from_date' => $from_date,
                        'to_date' => $to_date,
                        'subject' => $subject,
                        'invoiceList' => $invoiceList,
                        'total_due' => $total_due,
                        'summary_date' => $summary_date,
                        'summary_dd' => $summary_dd,
                        'summary_mm' => $summary_mm,
                        'summary_yyyy' => $summary_yyyy,
                        'summary_timestamp' => $summary_timestamp,
                        'UpdatedBy' => $this->AUTH_USER->id,
                        'updated_at' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'summary bill updated successfully!',
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

            //Insert a new Bill summary
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    bill_summary_list::Insert([
                        'Code' => $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        'user_id' => $user_id,
                        'summary_code' => $summary_code,
                        'bill_type' => $bill_type,
                        'client_id' => $client_id,
                        'from_date' => $from_date,
                        'to_date' => $to_date,
                        'subject' => $subject,
                        'invoiceList' => $invoiceList,
                        'total_due' => $total_due,
                        'summary_date' => $summary_date,
                        'summary_dd' => $summary_dd,
                        'summary_mm' => $summary_mm,
                        'summary_yyyy' => $summary_yyyy,
                        'summary_timestamp' => $summary_timestamp,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);
                    $SysCode->UpdateBillInvoiceNumber($client_id, $SysCode::client, $this->MODULE_CODE, $bill_type);

                    return response()->json([
                        'success' => true,
                        'message' => 'Bill Summary created successfully!',
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

    public function GetSingleBillSummary(Request $request)
    {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $summary_id = $request->input("summary_id");
            $summary_code = $request->input("summary_code");

            $bill_summary = bill_summary_list::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('IsDeleted', '=', 0);

            if($summary_id != '') {
                $bill_summary->where('summary_id', '=', $summary_id);
            } elseif($summary_code != '') {
                $bill_summary->where('summary_code', '=', $summary_code);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a Bill Summary id or code',
                ]);
            }

            if(!$bill_summary->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Bill summary was not found!',
                ]);
            }

            $bill_summary = $bill_summary->first();

            /**
             * Flags set to boolean for javascript
             */
            $bill_summary->IsDeleted = $bill_summary->IsDeleted == 1;
            $bill_summary->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Bill summary details got successfully!',
                'bill_summary_list' => $bill_summary,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    public function DeleteBillSummary(Request $request)
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

        $id = $request->input('summary_id');

        $CreatedAt = date("Y-m-d H:i:s", time());

        $bill_summary = bill_summary_list::where("summary_id", "=", $id)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$bill_summary->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested bill summary was not found!',
            ]);
        }

        try {
            if ($trash_mode === 1) {

                $bill_summary->delete();

            }

            else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $bill_summary->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'bill summary deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }


    }

    public function RestoreBillSummary(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $summary_id = $request->input("summary_id");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $bill_summary = bill_summary_list::where("summary_id", "=", $summary_id)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$bill_summary->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested bill summary was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["updated_at"] = $CreatedAt;
                $bill_summary->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'bill summary restored successfully!',
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

    public function GetUpdatedCode(Request $request)
    {
        $bill_type = $request->billtype ?? null;
        $client_id = $request->client_id ?? null;
        
        if(!empty($client_id) && !empty($bill_type))
        {
            $code = $this->NewDocCode($client_id, $bill_type);

            return response()->json([
                'success' => true,
                'billSummary_newCode' => $code,
            ]);
        }
        else
        {
            return response()->json([
                'success' => false,
                'billSummary_newCode' => "",
            ]);
        }
    }

}
