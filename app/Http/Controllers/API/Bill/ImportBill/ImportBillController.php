<?php

namespace App\Http\Controllers\API\Bill\ImportBill;

use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Models\Bill\bill_entry_list;
use App\Models\Bill\transport_bill_list;
use App\Models\Client\Client;
use App\Models\Bank\Bank;
use App\Models\Supplier\Supplier;
use App\Models\document\document;
use App\Models\enclosed_doc\enclosed_doc;
use App\Models\Charge_Category\Charge_Category;
use App\Models\Charge_Head\Charge_Head;
use App\Models\Company\Company;
use App\Models\Currency\Currency;
use App\Models\Port\Port;
use App\Models\Unit\Unit;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Validator,Redirect,Response,File;
use DateTime;
use \Exception;
use Illuminate\Support\Facades\Log;

class ImportBillController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['11'];
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
                'importbill_newCode' => $code,
            ]);
        }
        else
        {
            return response()->json([
                'success' => false,
                'importbill_newCode' => "",
            ]);
        }

    }

    public function GetAllImportBill(Request $request)
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
           $Import_Bill_List = bill_entry_list::where('bill_entry_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('bill_entry_lists.BranchID', '=', $this->AUTH_USER->BranchID)
               ->where('bill_entry_lists.IsDeleted', '=', $trash_mode)
               ->where('bill_entry_lists.bill_type', '=', 'Import')
               ->leftJoin('clients', 'bill_entry_lists.client_id', '=', 'clients.id')
               ->leftJoin('suppliers', 'bill_entry_lists.supplier_id', '=', 'suppliers.id');

           if ($search_query != "") {
               $Import_Bill_List->where(function($query)use($search_query){
                    return $query->where("bill_entry_lists.bill_code", "LIKE", "%" . $search_query . "%")
                        ->orWhere("bill_entry_lists.CreatedAt", "LIKE", "%" . $search_query . "%")
                        ->orWhere("bill_entry_lists.total_amount", "LIKE", "%" . $search_query . "%")
                        ->orWhere("clients.Name", "LIKE", "%" . $search_query . "%")
                        ->orWhere("suppliers.Name", "LIKE", "%" . $search_query . "%");
               });
           }

           $total_bill = $Import_Bill_List->get();

            $Import_Bill_List = $Import_Bill_List->select('bill_entry_lists.*','clients.Name as client_name', 'suppliers.Name as supplier_name')
            ->orderBy('bill_entry_lists.id', 'DESC')
            /*
            ->when($search_query != "", function($query) use ($skip){
                return $query->skip($skip);
            })
            ->take($row_limit)
            ->get();
            */
            // ->skip($skip)
            ->when(empty($search_query), function($query) use ($skip){
                return $query->skip($skip);
            })
            ->take($row_limit)
            ->get();

            foreach($Import_Bill_List as $bill)
            {
                $particular_charges = json_decode($bill->particulars_charges);
                $bill->voucher_total = (!empty($particular_charges)) ? array_sum(array_column($particular_charges, 'actual_amount')) : 0.00;
                $bill->voucher_total = (float)number_format((float)$bill->voucher_total, 2, '.', '');
            }


           return response()->json([
               'success' => true,
               'message' => 'import bill List got successfully!',
               'import_bill_list' => $Import_Bill_List,
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
    
    public function SaveImport(Request $request)
    {
       $file_array = array();
        $count_files = 0;
        $individual_file_name="";

        if($request->hasfile('attachment'))
        {
            foreach ($request->file('attachment') as $file)
            {
                $individual_file_extension = $file->extension();
                $individual_file_name = "C&F_ImportBill_DOC_" . "bill_code" . rand() . "_TIMESTAMP_" .time(). "." .$individual_file_extension;
                Storage::disk('public')->put('ImportBill/' . $individual_file_name, file_get_contents($file));
                array_push($file_array,$individual_file_name);
                $individual_file_name="";
                $count_files = $count_files + 1;
            }
        }

        $attachments = json_encode($file_array);
        // echo gettype($attachments);

        $bill_date = $request->input("bill_date");

        $bill_date = date_create_from_format("d/m/Y", $bill_date)->format("Y-m-d");

        $bill_timestamp = DateTime::createFromFormat('Y-m-d', $bill_date);

         if($bill_timestamp) {
             $bill_timestamp = $bill_timestamp->getTimestamp();
         }

         $bill_dd = date('d', $bill_timestamp);
         $bill_mm = date('m', $bill_timestamp);
         $bill_yyyy = date('Y', $bill_timestamp);

        $id = $request->input("id");
        $user_id = $this->AUTH_USER->id;
        $bill_type = "Import";
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

        $save_import = bill_entry_list::where('id', '=', $id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($save_import->exists()) {

            //Update Import_bill_list
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
                        $save_import->update([
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
                            'message' => 'Import bill updated successfully!',
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

            //Insert a new Import bill
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
                        'message' => 'Import Bill created successfully!'

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

    public function GetSingleImport(Request $request)
    {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $id = $request->input("id");
            $Code = $request->input("Code");

            $Import = bill_entry_list::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('IsDeleted', '=', 0);

            if($id != '') {
                $Import->where('id', '=', $id);
            } elseif($Code != '') {
                $Import->where('Code', '=', $Code);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a Import Bill id or code',
                ]);
            }

            if(!$Import->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Import Bill was not found!'
                ]);
            }

            $Import = $Import->first();
            if($Import->attachments!='[]')
            {
                $attachments = $Import->attachments;
                // dd($attachments);
                $attachments = explode(',',$attachments);
                for($i=0; $i<count($attachments); $i++)
                {
                    // echo $attachments[$i];
                    $process_attachments = str_replace('"','',$attachments[$i]);
                    $process_attachments = str_replace('[','',$process_attachments);
                    $process_attachments = str_replace(']','',$process_attachments);
                    $attachments[$i]=config('url_config.app_url') . '/ImportBill/' . $process_attachments;
                }
                $Import->attachments = json_encode($attachments);  
            }
            /**
             * Flags set to boolean for javascript
             */
            $Import->IsDeleted = $Import->IsDeleted == 1;
            $Import->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'import bill List details got successfully!',
                'import_bill_list' => $Import       
                ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    public function DeleteImport(Request $request)
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

        $Import = bill_entry_list::where("id", "=", $id)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$Import->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested import bill was not found!',
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

                $Import->delete();

            }

            else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $Import->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Import Bill deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }


    }

    public function DeleteAttachments(Request $request)
    {
        $id = $request->input("id");
        $bill_attachment = bill_entry_list::where("id", "=", $id)->first(); 
        try{
            $bill_attachment->update(["attachments" => '[]']);
            return response()->json([
                'success' => true,
                'attachments' => 'attachments deleted successfully!',
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }
    }

    public function RestoreImport(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $id = $request->input("id");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $import = bill_entry_list::where("id", "=", $id)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$import->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested import bill was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["updated_at"] = $CreatedAt;
                $import->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'import bill restored successfully!',
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

            $charge_head_dropdown = DB::table('charge_heads')->select('charge_heads.head_name','charge_heads.id')
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

        $delete_document_dropdown = enclosed_doc::where("id", "=", $id);

        if (!$delete_document_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested document_list bill was not found!',
            ]);
        }

        try {           
            $delete_document_dropdown->delete();

            $document_dropdown = DB::table('enclosed_docs')->select('enclosed_docs.doc_name','enclosed_docs.id')
            ->orderBy('enclosed_docs.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'document list deleted successfully!',
                'document_dropdown' => $document_dropdown
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

            $charge_category_dropdown = DB::table('charge_categories')->select('charge_categories.category_name','charge_categories.id')
            ->orderBy('charge_categories.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'charge category list deleted successfully!',
                'charge_category' => $charge_category_dropdown
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

            $charge_head_dropdown = DB::table('charge_heads')->select('charge_heads.head_name','charge_heads.id')
            ->orderBy('charge_heads.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'charge head list deleted successfully!',
                'charge_head' => $charge_head_dropdown
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    public function DeleteCurrencyDropDown(Request $request)
    {

        $id = $request->input('id');

        $delete_currency_dropdown = Currency::where("id", "=", $id);
        $existing_currency = bill_entry_list::where("currency_id", "=" , $id);

        if (!$delete_currency_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested currency was not found!',
            ]);
        }

        try {  
            if($existing_currency->exists())
            {
                $currency_dropdown = DB::table('currencies')->select('currencies.Name')
                ->orderBy('currencies.id')->get();

                return response()->json([
                    'success' => false,
                    'message' => 'This currency has already been used in another bill, unable to delete',
                    'currency' => $currency_dropdown
                ]);
            }
            else
            {
                $delete_currency_dropdown->delete();

                $currency_dropdown = DB::table('currencies')->select('currencies.Name')
                ->orderBy('currencies.id')->get();
    
                return response()->json([
                    'success' => true,
                    'message' => 'currency deleted successfully!',
                    'currency' => $currency_dropdown
                ]);
            }         
            

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    public function DeletePortDropDown(Request $request)
    {

        $id = $request->input('id');

        $delete_port_dropdown = Port::where("id", "=", $id);
        $existing_port = bill_entry_list::where("port_id", "=" , $id);

        if (!$delete_port_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested port was not found!',
            ]);
        }

        try {  
            if($existing_port->exists())
            {
                $port_dropdown = DB::table('ports')->select('ports.Name')
                ->orderBy('ports.id')->get();

                return response()->json([
                    'success' => false,
                    'message' => 'This port has already been used in another bill, unable to delete',
                    'port' => $port_dropdown
                ]);
            }
            else
            {
                $delete_port_dropdown->delete();

                $port_dropdown = DB::table('ports')->select('ports.Name')
                ->orderBy('ports.id')->get();
    
                return response()->json([
                    'success' => true,
                    'message' => 'port deleted successfully!',
                    'port' => $port_dropdown
                ]);
            }         
            

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    public function DeleteUnitDropDown(Request $request)
    {

        $id = $request->input('id');

        $delete_unit_dropdown = Unit::where("id", "=", $id);
        $existing_unit_bill_entry_list = bill_entry_list::where("unit_id", "=" , $id);
        $existing_unit_transport_bill_list = transport_bill_list::where("unit_id", "=" , $id);

        if (!$delete_unit_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested unit was not found!',
            ]);
        }

        try {  
            if($existing_unit_bill_entry_list->exists() || $existing_unit_transport_bill_list->exists())
            {
                $unit_dropdown = DB::table('units')->select('units.Name')
                ->orderBy('units.id')->get();

                return response()->json([
                    'success' => false,
                    'message' => 'This unit has already been used in another bill, unable to delete',
                    'unit' => $unit_dropdown
                ]);
            }
            else
            {
                $delete_unit_dropdown->delete();

                $unit_dropdown = DB::table('units')->select('units.Name')
                ->orderBy('units.id')->get();
    
                return response()->json([
                    'success' => true,
                    'message' => 'unit deleted successfully!',
                    'unit' => $unit_dropdown
                ]);
            }         
            

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    /** 
     * Add Dropdown APIs
     */

    public function AddDocumentDropDown(Request $request)
    {
        $doc_name = $request->input("doc_name");
        try{
            enclosed_doc::Insert([
                'doc_name' => $doc_name
            ]);

            $document_dropdown = DB::table('enclosed_docs')->select('enclosed_docs.doc_name','enclosed_docs.id')
            ->orderBy('enclosed_docs.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'new document created successfully!',
                'document_dropdown' => $document_dropdown

            ]);
        }catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to create! ' . $exception->getMessage(),
            ]);
        }

    }

    public function AddChargeCategoryDropDown(Request $request)
    {

        $category_name = $request->input('category_name');

        try {           
            Charge_Category::Insert([
                'category_name' => $category_name
            ]);

            $charge_category_dropdown = DB::table('charge_categories')->select('charge_categories.category_name','charge_categories.id')
            ->orderBy('charge_categories.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'charge category list added successfully!',
                'charge_category' => $charge_category_dropdown
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }

    }

    public function AddChargeHeadDropDown(Request $request)
    {

        $head_name = $request->input('head_name');

        try {           
            Charge_Head::Insert([
                'head_name' => $head_name
            ]);

            $charge_head_dropdown = DB::table('charge_heads')->select('charge_heads.head_name','charge_heads.id')
            ->orderBy('charge_heads.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'charge head added successfully!',
                'charge_head' => $charge_head_dropdown
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