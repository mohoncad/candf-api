<?php

namespace App\Http\Controllers\API\Bill\BillPayment;

use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Models\Bill\bill_payment_list;
use App\Models\Bill\bill_entry_list;
use App\Models\Bill\transport_bill_list;
use App\Models\Bill\bill_summary_list;
use App\Models\Company\Company;
use App\User;
use Illuminate\Http\Request;
use Validator, Redirect, Response, File;
use DateTime;
use Exception;
use PhpParser\Node\Expr\List_;

class BillPaymentController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['16'];
            $this->MODULE_PERMISSIONS = UAPController::ModulePermissions($this->MODULE_CODE);
            $this->MODULE_ACCESSIBLE = ($this->MODULE_PERMISSIONS === true || (gettype($this->MODULE_PERMISSIONS) === 'object' && $this->MODULE_PERMISSIONS->ModuleAccess));
            if (!$this->MODULE_ACCESSIBLE) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied!!! You need permission to perform this action!',
                ], 403);
            }

            return $next($request);
        });
    }

    public function NewDocCode($client_id)
    {
        $company = Company::find($this->AUTH_USER->CompanyID);
        $SysCodeC = new SysCodeController();
        
        if($company->InvoiceNumberType == "generic")
        {
            return $SysCodeC->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH);
        }
        else
        {
            return $SysCodeC->getNewBillInvoiceNumber($client_id, $SysCodeC::client,$this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH);
        }
    }

    public function GetUpdatedCode(Request $request)
    {
        $client_id = $request->client_id ?? null;
        if(!empty($client_id))
        {
            $code = $this->NewDocCode($client_id);

            return response()->json([
                'success' => true,
                'billPayment_newCode' => $code,
            ]);
        }
        else
        {
            return response()->json([
                'success' => false,
                'billPayment_newCode' => "",
            ]);
        }
    }

    public function GetAllBillPayment(Request $request)
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
            $bill_payment_list = bill_payment_list::where('bill_payment_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('bill_payment_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('bill_payment_lists.IsDeleted', '=', $trash_mode)
                ->leftJoin('clients', 'bill_payment_lists.client_id', '=', 'clients.id');

            if ($search_query != "") {
                $bill_payment_list->where("bill_payment_lists.bill_pmt_code", "LIKE", "%" . $search_query . "%")
                    ->orWhere("bill_payment_lists.pmt_date", "LIKE", "%" . $search_query . "%")
                    ->orWhere("bill_payment_lists.pmt_type", "LIKE", "%" . $search_query . "%")
                    ->orWhere("bill_payment_lists.total_amount", "LIKE", "%" . $search_query . "%")
                    ->orWhere("clients.Name", "LIKE", "%" . $search_query . "%");
            }

            $total_bill_payment_list = $bill_payment_list->get();

            $bill_payment_list = $bill_payment_list->select('bill_payment_lists.*', 'clients.Name as client_name')
                ->orderBy('bill_payment_lists.bill_pmt_id', 'DESC')
                ->when($search_query != "", function ($query) use ($skip) {
                    return $query->skip($skip);
                })
                ->take($row_limit)
                ->get();


            return response()->json([
                'success' => true,
                'message' => 'bill payment List got successfully!',
                'bill_payment_list' => $bill_payment_list,
                'total_bill_payment_list' => $total_bill_payment_list->count()
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed! ' . $exception->getMessage(),
            ]);
        }
    }

    public function SaveBillPayment(Request $request)
    {
        $pmt_date = $request->input("pmt_date");
        $pmt_date = date_create_from_format("d/m/Y", $pmt_date)->format("Y-m-d");

        $pmt_timestamp = DateTime::createFromFormat('Y-m-d', $pmt_date);

        if ($pmt_timestamp) {
            $pmt_timestamp = $pmt_timestamp->getTimestamp();
        }

        $pmt_dd = date('d', $pmt_timestamp);
        $pmt_mm = date('m', $pmt_timestamp);
        $pmt_yyyy = date('Y', $pmt_timestamp);

        $bill_pmt_code = $request->input("bill_pmt_code");
        $user_id = $this->AUTH_USER->id;
        $client_id = $request->input("client_id");

        $bill_list_source = $request->input("bill_list_source");     
        $array_bill_list_source = json_encode($bill_list_source); 

        $total_amount = $request->input("total_amount");
        $less_amount = $request->input("less_amount");
        $payment_amount = $request->input("payment_amount");
        $pmt_type = $request->input("pmt_type");
        $CreatedAt = date("Y-m-d H:i:s", time()); 

        //Insert a new Bill payment

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
            $SysCode = new SysCodeController();
            try {
                bill_payment_list::Insert([
                    'Code' => $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                    'CompanyID' => $this->AUTH_USER->CompanyID,
                    'BranchID' => $this->AUTH_USER->BranchID,
                    'bill_pmt_code' => $bill_pmt_code,
                    'user_id' => $user_id,
                    'client_id' => $client_id,
                    'bill_list_source' => $array_bill_list_source,
                    'total_amount' => $total_amount,
                    'less_amount' => $less_amount,
                    'payment_amount' => $payment_amount,
                    'pmt_type' => $pmt_type,
                    'pmt_dd' => $pmt_dd,
                    'pmt_mm' => $pmt_mm,
                    'pmt_yyyy' => $pmt_yyyy,
                    'pmt_date' => $pmt_date,
                    'pmt_timestamp' => $pmt_timestamp,
                    'CreatedBy' => $this->AUTH_USER->id,
                    'CreatedAt' => $CreatedAt,
                ]);

                $SysCode->UpdateIncrement($this->MODULE_CODE);
                $SysCode->UpdateBillInvoiceNumber($client_id, $SysCode::client, $this->MODULE_CODE);

                 foreach($bill_list_source as $b){
                     $billCode = substr($b["bill_code"],0,3);
                    if($billCode=="IMB" || $billCode=="EXB")
                    {
                        $bill = bill_entry_list::where("id","=", $b["id"])->first();
                        $bill->full_paid = 1;
                        $bill->save();
                    }
                    else if($billCode=="TPB")
                    {
                        $bill = transport_bill_list::where("bill_id","=", $b["bill_id"])->first();
                        $bill->full_paid = 1;
                        $bill->save();
                    }          
                 }

                return response()->json([
                    'success' => true,
                    'message' => 'Bill payment created successfully!',
                ]);
            } catch (Exception $exception) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DB_ERROR',
                    'message' => 'Failed to create!!! ' . $exception->getMessage(),
                    'bill' => $bill_list_source
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

    public function GetSingleBillPayment(Request $request)
    {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $bill_pmt_id = $request->input("bill_pmt_id");
            $bill_pmt_code = $request->input("bill_pmt_code");

            $bill_payment = bill_payment_list::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('IsDeleted', '=', 0);

            if ($bill_pmt_id != '') {
                $bill_payment->where('bill_pmt_id', '=', $bill_pmt_id);
            } elseif ($bill_pmt_code != '') {
                $bill_payment->where('bill_pmt_code', '=', $bill_pmt_code);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a Bill payment id or code',
                ]);
            }

            if (!$bill_payment->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Bill payment was not found!',
                ]);
            }

            $bill_payment = $bill_payment->first();

            /**
             * Flags set to boolean for javascript
             */
            $bill_payment->IsDeleted = $bill_payment->IsDeleted == 1;
            $bill_payment->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Bill payment details got successfully!',
                'bill_payment_list' => $bill_payment,
            ]);
        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    public function DeleteBillPayment(Request $request)
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

        $bill_pmt_id = $request->input('bill_pmt_id');

        $CreatedAt = date("Y-m-d H:i:s", time());

        $bill_payment = bill_payment_list::where("bill_pmt_id", "=", $bill_pmt_id)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$bill_payment->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested bill payment was not found!',
            ]);
        }

        try {
            if ($trash_mode === 1) {
                $single_list = $bill_payment->first();
                $single_list = json_decode($single_list->bill_list_source);
                foreach($single_list as $list)
                {
                    $billCode = substr($list->bill_code,0,3);
                    if($billCode == "IMB" || $billCode == "EXB")
                    {
                        $bill = bill_entry_list::where("id","=",$list->id)->first();
                        if($bill != null)
                        {
                            $bill->full_paid = 0;
                            $bill->save();
                        }
                    }
                    else if($billCode == "TPB")
                    {
                        $bill = transport_bill_list::where("bill_id","=",$list->bill_id)->first();
                        if($bill != null)
                        {
                            $bill->full_paid = 0;
                            $bill->save();
                        }
                    }
                }
                $bill_payment->delete();
            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $bill_payment->update($data);
            }

            return response()->json([
                'success' => true,
                'message' => 'bill payment deleted successfully!',
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);
        }
    }

    public function RestoreBillPayment(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $bill_pmt_id = $request->input("bill_pmt_id");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $bill_payment = bill_payment_list::where("bill_pmt_id", "=", $bill_pmt_id)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$bill_payment->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested bill payment was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["updated_at"] = $CreatedAt;
                $bill_payment->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'bill payment restored successfully!',
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

    public function GetClientWiseBill(Request $request)
    {
        $trash_mode = $request->input('trash_mode');
        $trash_mode = $trash_mode === 'true' ? 1 : 0;

        $client_id = $request->input("client_id");
        try {
            $client_wise_import_bill =  bill_entry_list::where('bill_entry_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('bill_entry_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('bill_entry_lists.IsDeleted', '=', $trash_mode)
                ->where('bill_entry_lists.bill_type', '=', 'Import')
                ->where('bill_entry_lists.full_paid', '=', 0)
                ->where('bill_entry_lists.client_id', '=', $client_id);

            $client_wise_import_bill = $client_wise_import_bill->get();

            $client_wise_export_bill =  bill_entry_list::where('bill_entry_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('bill_entry_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('bill_entry_lists.IsDeleted', '=', $trash_mode)
                ->where('bill_entry_lists.bill_type', '=', 'Export')
                ->where('bill_entry_lists.full_paid', '=', 0)
                ->where('bill_entry_lists.client_id', '=', $client_id);

            $client_wise_export_bill = $client_wise_export_bill->get();

            $client_wise_transport_bill =  transport_bill_list::where('transport_bill_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('transport_bill_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('transport_bill_lists.IsDeleted', '=', $trash_mode)
                ->where('transport_bill_lists.full_paid', '=', 0)
                ->where('transport_bill_lists.client_id', '=', $client_id);

            $client_wise_transport_bill = $client_wise_transport_bill->get();

            return response()->json([
                'success' => true,
                'message' => 'client wise import,export & transport bill list got successfully!',
                'client_wise_import_bill' => $client_wise_import_bill,
                'client_wise_export_bill' => $client_wise_export_bill,
                'client_wise_transport_bill' => $client_wise_transport_bill
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed! ' . $exception->getMessage(),
            ]);
        }
    }

    public function GetClientWiseBillSummary(Request $request)
    {
        $trash_mode = $request->input('trash_mode');
        $trash_mode = $trash_mode === 'true' ? 1 : 0;

        $client_id = $request->input("client_id");
        try {
            $client_wise_bill_summary =  bill_summary_list::where('bill_summary_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('bill_summary_lists.BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('bill_summary_lists.IsDeleted', '=', $trash_mode)
                ->where('bill_summary_lists.client_id', '=', $client_id);

            $client_wise_bill_summary = $client_wise_bill_summary->get();

            return response()->json([
                'success' => true,
                'message' => 'client wise bill summary list got successfully!',
                'client_wise_bill_summary' => $client_wise_bill_summary
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed! ' . $exception->getMessage(),
            ]);
        }
    }

    public function GetSummaryWiseBill(Request $request)
    {
        $summary_id = $request->input("summary_id");

        $summary_wise_bill = bill_summary_list::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID)
            ->where('IsDeleted', '=', 0);

        if ($summary_id != '') {
            $summary_wise_bill->where('summary_id', '=', $summary_id);
        } else {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_ID_OR_CODE',
                'message' => 'You must provide a Bill summary id or code',
            ]);
        }

        if (!$summary_wise_bill->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'Bill summary was not found!'
            ]);
        }

        $summary_wise_bill = $summary_wise_bill->first();
        $invoiceList = json_decode($summary_wise_bill->InvoiceList);
        $transportBill = array();
        $importOrExportBill = array();
        if($summary_wise_bill->bill_type=="Transport")
        {
            foreach($invoiceList as $invoice)
            {
                $bill = transport_bill_list::where('bill_id','=',$invoice)->where('full_paid','=',0)->first();
                if($bill!=null)
                {
                    array_push($transportBill,$bill);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'summary wise bill list successfully!',
                'summary_wise_bill_list' => $transportBill
            ]);
        }
        
        else if($summary_wise_bill->bill_type=="Import" || $summary_wise_bill->bill_type=="Export")
        {
            foreach($invoiceList as $invoice)
            {
                $bill = bill_entry_list::where('id','=',$invoice)->where('full_paid','=',0)->first();
                if($bill!=null)
                {
                    array_push($importOrExportBill,$bill);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'summary wise bill list successfully!',
                'summary_wise_bill_list' => $importOrExportBill
            ]);
        }
    }
}
