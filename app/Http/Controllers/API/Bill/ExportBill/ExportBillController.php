<?php

namespace App\Http\Controllers\API\Bill\ExportBill;

use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Models\Bill\bill_entry_list;
use App\Models\Client\Client;
use App\Models\Bank\Bank;
use App\Models\Company\Company;
use App\Models\Supplier\Supplier;
use App\Models\document\document;
use App\Models\Charge_Category\Charge_Category;
use App\Models\Charge_Head\Charge_Head;use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Validator,Redirect,Response,File;
use DateTime;
use \Exception;


class ExportBillController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['12'];
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

    /** 
     * Basic APIs
     */

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
                'exportbill_newCode' => $code,
            ]);
        }
        else
        {
            return response()->json([
                'success' => false,
                'exportbill_newCode' => "",
            ]);
        }
    }

    public function GetAllExportBill(Request $request) 
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
           $Export_Bill_List = bill_entry_list::where('bill_entry_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('bill_entry_lists.BranchID', '=', $this->AUTH_USER->BranchID)
               ->where('bill_entry_lists.IsDeleted', '=', $trash_mode)
               ->where('bill_entry_lists.bill_type', '=', 'Export')
               ->leftJoin('clients', 'bill_entry_lists.client_id', '=', 'clients.id')
               ->leftJoin('suppliers', 'bill_entry_lists.supplier_id', '=', 'suppliers.id');

           if ($search_query != "") {
               $Export_Bill_List->where(function($query)use($search_query){
                    return $query->where("bill_entry_lists.bill_code", "LIKE", "%" . $search_query . "%")
                        ->orWhere("bill_entry_lists.CreatedAt", "LIKE", "%" . $search_query . "%")
                        ->orWhere("bill_entry_lists.total_amount", "LIKE", "%" . $search_query . "%")
                        ->orWhere("clients.Name", "LIKE", "%" . $search_query . "%")
                        ->orWhere("suppliers.Name", "LIKE", "%" . $search_query . "%");
               });
           }

           $total_bill = $Export_Bill_List->get();

            $Export_Bill_List = $Export_Bill_List->select('bill_entry_lists.*','clients.Name as client_name', 'suppliers.Name as supplier_name')
            ->orderBy('bill_entry_lists.id', 'DESC')
            // ->skip($skip)
            ->when(empty($search_query), function($query) use ($skip){
                return $query->skip($skip);
            })
            ->take($row_limit)
            ->get();

            foreach($Export_Bill_List as $bill)
            {
                $particular_charges = json_decode($bill->particulars_charges);
                $bill->voucher_total = (!empty($particular_charges)) ? array_sum(array_column($particular_charges, 'actual_amount')) : 0.00;
                $bill->voucher_total = (float)number_format((float)$bill->voucher_total, 2, '.', '');
            }


           return response()->json([
               'success' => true,
               'message' => 'Export bill List got successfully!',
               'export' => $Export_Bill_List,
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

    public function SaveExport(Request $request) 
    {

        $input_files = $request->file('attachment');         
        $file_array = array();
        $count_files = 0;
        $individual_file_name="";

        if($input_files!=null)
        {
            foreach ($input_files as $file) 
            {                 
                $individual_file_extension = $request->attachment[$count_files]->extension();
                $individual_file_name = "C&F_ExportBill_DOC_" . "bill_code" . rand() . "_TIMESTAMP_" .time(). "." .$individual_file_extension; 
                Storage::disk('public')->put('ExportBill/' . $individual_file_name, file_get_contents($file));
                array_push($file_array,$individual_file_name);
                $individual_file_name="";
                $count_files = $count_files + 1;    
            }
        }
        

        $attachments = json_encode($file_array);

        $bill_date = $request->input("bill_date");

        $bill_date = date_create_from_format("d/m/Y", $bill_date)->format("Y-m-d");

        // echo $bill_date;

        $bill_timestamp = DateTime::createFromFormat('Y-m-d', $bill_date);
         if($bill_timestamp) {
             $bill_timestamp = $bill_timestamp->getTimestamp();
         }

         $bill_dd = date('d', $bill_timestamp);
         $bill_mm = date('m', $bill_timestamp);
         $bill_yyyy = date('Y', $bill_timestamp);

        $id = $request->input("id");
        $user_id = $this->AUTH_USER->id;
        $bill_type = "Export";
        $bill_code = $request->input("bill_code");
        $client_address = $request->input("client_address") ?: "";
        $client_id = $request->input("client_id");
        $supplier_id = $request->input("supplier_id");
        $client_bank_name = $request->input("client_bank_name");
        $port_id = $request->input("port_id") ?: 0;
        $carrier = $request->input("carrier") ?: "";
        $mawb_no = $request->input("mawb_no") ?: "";
        $mawb_date = $request->input("mawb_date") ?: "";
        $hawb_no = $request->input("hawb_no") ?: "";
        $hawb_date = $request->input("hawb_date") ?: "";
        $lc_no = $request->input("lc_no") ?: "";
        $lc_date = $request->input("lc_date") ?: "";
        $be_no = $request->input("be_no") ?: "";
        $be_date = $request->input("be_date") ?: "";
        $invoice_no = $request->input("invoice_no") ?: "";
        $invoice_date = $request->input("invoice_date") ?: "";
        $commodity = $request->input("commodity") ?: "";
        $currency_id = $request->input("currency_id") ?: 0;
        $currency_rate = $request->input("currency_rate") ?: "";
        $invoice_value = $request->input("invoice_value") ?: "";
        $a_value = $request->input("a_value") ?: "";
        $gross_weight = $request->input("gross_weight") ?: "";
        $net_weight = $request->input("net_weight") ?: "";
        $unit_id = $request->input("unit_id") ?: 0;
        $quantity = $request->input("quantity") ?: "";
        $goods_description = $request->input("goods_description") ?: "";
        $enc_docs = $request->input("enc_docs") ?: "";
        $particulars_charges = $request->input("particulars_charges") ?: "";
        $total_amount = $request->input("total_amount") ?: 0;
        $paid_amount = $request->input("paid_amount") ?: 0;
        $due_amount = $request->input("due_amount") ?: 0;
        $full_paid = (((float) $total_amount > 0)) ? (((float) $due_amount > 0) ? 0 : 1) : 0;
        $bill_note = $request->input("bill_note") ?: "";
        $job_date = "";
        $CreatedAt = date("Y-m-d H:i:s", time());

        $save_export = bill_entry_list::where('id', '=', $id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($save_export->exists()) {

            //Update Export_bill_list
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                //if(full_paid status is not pending but paid) msg("you are not allowed to update this")
                $get_status = bill_entry_list::where("id", "=", $id)->first();
                
                if($get_status->full_paid == 1)
                {
                    return response()->json([
                        'success' => false,
                        'message' => 'you are not allowed to update this!',
                    ]);
                }
                else
                {
                    try {
                        $save_export->update([
                            'user_id' => $user_id,                     
                            'bill_type' => $bill_type,
                            'bill_code' => $bill_code,
                            'client_id' => $client_id,
                            'client_address' => $client_address,
                            'supplier_id' => $supplier_id,
                            'client_bank_name' => $client_bank_name,
                            'port_id' => $port_id,
                            'carrier' => $carrier,
                            'mawb_no' => $mawb_no,
                            'mawb_date' => $mawb_date,
                            'hawb_no' => $hawb_no,
                            'hawb_date' => $hawb_date,
                            'lc_no' => $lc_no,
                            'lc_date' => $lc_date,
                            'be_no' => $be_no,
                            'be_date' => $be_date,
                            'invoice_no' => $invoice_no,
                            'invoice_date' => $invoice_date,
                            'commodity' => $commodity,
                            'currency_id' => $currency_id,
                            'currency_rate' => $currency_rate,
                            'invoice_value' => $invoice_value,
                            'a_value' => $a_value,
                            'gross_weight' => $gross_weight,
                            'net_weight' => $net_weight,
                            'unit_id' => $unit_id,
                            'quantity' => $quantity,
                            'goods_description' => $goods_description,
                            'enc_docs' => $enc_docs,
                            'particulars_charges' => $particulars_charges,
                            'attachments' => $attachments,
                            'total_amount' => $total_amount,
                            'paid_amount' => $paid_amount,
                            'due_amount' => $due_amount,
                            'full_paid' => $full_paid,
                            'bill_note' => $bill_note,
                            'job_date' => $job_date,
                            'bill_dd' => $bill_dd,
                            'bill_mm' => $bill_mm,
                            'bill_yyyy' => $bill_yyyy,
                            'bill_date' => $bill_date,
                            'bill_timestamp' => $bill_timestamp,
                            'UpdatedBy' => $this->AUTH_USER->id,
                            'updated_at' => $CreatedAt,
                        ]);
    
                        return response()->json([
                            'success' => true,
                            'message' => 'Export bill updated successfully!',
                        ]);
                    } catch (Exception $exception) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'DB_ERROR',
                            'message' => 'Failed to update! ' . $exception->getMessage(),
                        ]);
                    }
                }
                

            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

        } else {

            //Insert a new Export bill
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    bill_entry_list::Insert([
                        'Code' => $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        'user_id' => $user_id,                     
                        'bill_type' => $bill_type,
                        'bill_code' => $bill_code,
                        'client_id' => $client_id,
                        'client_address' => $client_address,
                        'supplier_id' => $supplier_id,
                        'client_bank_name' => $client_bank_name,
                        'port_id' => $port_id,
                        'carrier' => $carrier,
                        'mawb_no' => $mawb_no,
                        'mawb_date' => $mawb_date,
                        'hawb_no' => $hawb_no,
                        'hawb_date' => $hawb_date,
                        'lc_no' => $lc_no,
                        'lc_date' => $lc_date,
                        'be_no' => $be_no,
                        'be_date' => $be_date,
                        'invoice_no' => $invoice_no,
                        'invoice_date' => $invoice_date,
                        'commodity' => $commodity,
                        'currency_id' => $currency_id,
                        'currency_rate' => $currency_rate,
                        'invoice_value' => $invoice_value,
                        'a_value' => $a_value,
                        'gross_weight' => $gross_weight,
                        'net_weight' => $net_weight,
                        'unit_id' => $unit_id,
                        'quantity' => $quantity,
                        'goods_description' => $goods_description,
                        'enc_docs' => $enc_docs,
                        'particulars_charges' => $particulars_charges,
                        'attachments' => $attachments,
                        'total_amount' => $total_amount,
                        'paid_amount' => $paid_amount,
                        'due_amount' => $due_amount,
                        'full_paid' => $full_paid,
                        'bill_note' => $bill_note,
                        'job_date' => $job_date,
                        'bill_dd' => $bill_dd,
                        'bill_mm' => $bill_mm,
                        'bill_yyyy' => $bill_yyyy,
                        'bill_date' => $bill_date,
                        'bill_timestamp' => $bill_timestamp,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);
                    $SysCode->UpdateBillInvoiceNumber($client_id, $SysCode::client, $this->MODULE_CODE);

                    return response()->json([
                        'success' => true,
                        'message' => 'Export Bill created successfully!',
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

    public function GetSingleExport(Request $request) 
    {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $id = $request->input("id");
            $Code = $request->input("Code");

            $Export = bill_entry_list::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('IsDeleted', '=', 0);

            if($id != '') {
                $Export->where('id', '=', $id);
            } elseif($Code != '') {
                $Export->where('Code', '=', $Code);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a Export Bill id or code',
                ]);
            }

            if(!$Export->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Export Bill was not found!',
                ]);
            }

            $Export = $Export->first();
            if($Export->attachments!='[]')
            {
                $attachments = $Export->attachments;
                // dd($attachments);
                $attachments = explode(',',$attachments);
                for($i=0; $i<count($attachments); $i++)
                {
                    // echo $attachments[$i];
                    $process_attachments = str_replace('"','',$attachments[$i]);
                    $process_attachments = str_replace('[','',$process_attachments);
                    $process_attachments = str_replace(']','',$process_attachments);
                    $attachments[$i]=config('url_config.app_url') . '/ExportBill/' . $process_attachments;
                }
                $Export->attachments = json_encode($attachments);
            }
            
            /**
             * Flags set to boolean for javascript
             */
            $Export->IsDeleted = $Export->IsDeleted == 1;
            $Export->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Export Bill details got successfully!',
                'Export' => $Export,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    public function DeleteExport(Request $request)
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

        $id = $request->input('id');

        $CreatedAt = date("Y-m-d H:i:s", time());

        $Export = bill_entry_list::where("id", "=", $id)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$Export->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested Export bill was not found!',
            ]);
        }

        try {
            //if(not pending) msg("you are not allowed to delete this")
            $get_status = bill_entry_list::where("id", "=", $id)->first();
            if($get_status->full_paid == 1)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'This bill is closed!',
                ]);
            }

            else if ($trash_mode === 1) {

                $Export->delete();

            } 
            
            else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $Export->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Export Bill deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }


    }

    public function RestoreExport(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $id = $request->input("id");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $Export = bill_entry_list::where("id", "=", $id)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$Export->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested export bill was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["updated_at"] = $CreatedAt;
                $Export->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Export bill restored successfully!',
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

    /** 
     * Get Dropdown APIs
     */

    public function GetClientDropDown(Request $request)
    {

       try {

            $client_dropdown = DB::table('clients')->select('clients.Code','clients.Name')
            ->orderBy('clients.id')->get();

           return response()->json([
               'success' => true,
               'message' => 'client dropdown List got successfully!',
               'client_dropdown_list' => $client_dropdown,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetBankDropDown(Request $request)
    {

        try {

            $bank_dropdown = DB::table('bank_list')->select('bank_list.Code','bank_list.Name')
            ->orderBy('bank_list.id')->get();

           return response()->json([
               'success' => true,
               'message' => 'bank dropdown List got successfully!',
               'bank_dropdown_list' => $bank_dropdown,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetSupplierDropDown(Request $request)
    {

        try {

            $supplier_dropdown = DB::table('suppliers')->select('suppliers.Code','suppliers.Name')
            ->orderBy('suppliers.id')->get();

           return response()->json([
               'success' => true,
               'message' => 'supplier dropdown List got successfully!',
               'supplier_dropdown_list' => $supplier_dropdown,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetDocumentDropDown(Request $request)
    {

        try {

            $document_dropdown = DB::table('enclosed_docs')->select('enclosed_docs.doc_name','enclosed_docs.id')
            ->orderBy('enclosed_docs.id')->get();

           return response()->json([
               'success' => true,
               'message' => 'document dropdown List got successfully!',
               'document_dropdown_list' => $document_dropdown,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetChargeCategoryDropDown(Request $request)
    {

        try {

            $charge_category_dropdown = DB::table('charge_categories')->select('charge_categories.category_name','charge_categories.id')
            ->orderBy('charge_categories.id')->get();

           return response()->json([
               'success' => true,
               'message' => 'charge_categories dropdown List got successfully!',
               'charge_categories_dropdown_list' => $charge_category_dropdown,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetChargeHeadDropDown(Request $request)
    {

        try {

            $charge_head_dropdown = DB::table('charge_heads')->select('charge_heads.head_name')
            ->orderBy('charge_heads.id')->get();

           return response()->json([
               'success' => true,
               'message' => 'charge_heads dropdown List got successfully!',
               'charge_heads_dropdown_list' => $charge_head_dropdown,
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
     * Delete Dropdown APIs
     */

    public function DeleteBankDropDown(Request $request)
    {
        $id = $request->input('id');

        $delete_bank_dropdown = Bank::where("id", "=", $id);

        if (!$delete_bank_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested bank_list bill was not found!',
            ]);
        }

        try {           
            $delete_bank_dropdown->delete();
            return response()->json([
                'success' => true,
                'message' => 'bank list deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }
    }

    public function DeleteSupplierDropDown(Request $request)
    {

        $id = $request->input('id');

        $delete_supplier_dropdown = Supplier::where("id", "=", $id);

        if (!$delete_supplier_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested supplier_list bill was not found!',
            ]);
        }

        try {           
            $delete_supplier_dropdown->delete();
            return response()->json([
                'success' => true,
                'message' => 'supplier list deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    public function DeleteDocumentDropDown(Request $request)
    {

        $id = $request->input('id');

        $delete_document_dropdown = document::where("id", "=", $id);

        if (!$delete_document_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested document_list bill was not found!',
            ]);
        }

        try {           
            $delete_document_dropdown->delete();
            return response()->json([
                'success' => true,
                'message' => 'document list deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    public function DeleteChargeCategoryDropDown(Request $request)
    {

        $id = $request->input('id');

        $delete_charge_category_dropdown = Charge_Category::where("id", "=", $id);

        if (!$delete_charge_category_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested charge_category_list bill was not found!',
            ]);
        }

        try {           
            $delete_charge_category_dropdown->delete();
            return response()->json([
                'success' => true,
                'message' => 'charge category list deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    public function DeleteChargeHeadDropDown(Request $request)
    {

        $id = $request->input('id');

        $delete_charge_head_dropdown = Charge_Head::where("id", "=", $id);

        if (!$delete_charge_head_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested charge_head_list bill was not found!',
            ]);
        }

        try {           
            $delete_charge_head_dropdown->delete();
            return response()->json([
                'success' => true,
                'message' => 'charge head list deleted successfully!',
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