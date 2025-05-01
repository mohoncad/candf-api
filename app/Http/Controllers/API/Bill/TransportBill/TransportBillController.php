<?php

namespace App\Http\Controllers\API\Bill\TransportBill;

use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Models\Bill\transport_bill_list;
use App\Models\Client\Client;
use App\Models\Charge_Head\Charge_Head;
use App\Models\Company\Company;
use App\Models\Location\Location;
use App\Models\Unit\Unit;
use App\Models\Vehicle\Vehicle;
use Illuminate\Support\Facades\DB;
use App\User;
use Illuminate\Http\Request;
use Validator,Redirect,Response,File;
use DateTime;
use \Exception;

class TransportBillController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['13'];
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
                'transportbill_newCode' => $code,
            ]);
        }
        else
        {
            return response()->json([
                'success' => false,
                'transportbill_newCode' => "",
            ]);
        }
    }


    public function GetAllTransportBill(Request $request)
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
           $Transport_Bill_List = transport_bill_list::where('transport_bill_lists.CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('transport_bill_lists.BranchID', '=', $this->AUTH_USER->BranchID)
               ->where('transport_bill_lists.IsDeleted', '=', $trash_mode)
               ->leftJoin('clients', 'transport_bill_lists.client_id', '=', 'clients.id');

           if ($search_query != "") {
               $Transport_Bill_List->where("transport_bill_lists.bill_code", "LIKE", "%" . $search_query . "%")
                                ->orWhere("transport_bill_lists.CreatedAt", "LIKE", "%" . $search_query . "%")
                                ->orWhere("transport_bill_lists.total_amount", "LIKE", "%" . $search_query . "%")
                                ->orWhere("transport_bill_lists.paid_amount", "LIKE", "%" . $search_query . "%")
                                ->orWhere("transport_bill_lists.due_amount", "LIKE", "%" . $search_query . "%")
                                ->orWhere("clients.Name", "LIKE", "%" . $search_query . "%");           
            }

            $total_bill = $Transport_Bill_List->get();

            $Transport_Bill_List = $Transport_Bill_List->select('transport_bill_lists.*','clients.Name as client_name')
            ->orderBy('transport_bill_lists.bill_id', 'DESC')
            // ->skip($skip)
            ->when(empty($search_query), function($query) use ($skip){
                return $query->skip($skip);
            })
            ->take($row_limit)
            ->get();

           return response()->json([
               'success' => true,
               'message' => 'transport bill List got successfully!',
               'transport_bill_list' => $Transport_Bill_List,
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

    public function SaveTransport(Request $request)
    {
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

        $user_id = $this->AUTH_USER->id;
        $bill_id = $request->input("bill_id");
        $bill_code = $request->input("bill_code");
        $client_id = $request->input("client_id");
        $delivery_challan_no = $request->input("delivery_challan_no") ?: "";
        $delivery_challan_date = $request->input("delivery_challan_date") ?: "";
        $transport_challan_no = $request->input("transport_challan_no") ?: "";
        $transport_challan_date = $request->input("transport_challan_date") ?: "";
        $lc_no = $request->input("lc_no") ?: "";
        $lc_date = $request->input("lc_date") ?: "";
        $be_no = $request->input("be_no") ?: "";
        $be_date = $request->input("be_date") ?: "";
        $holding_no = $request->input("holding_no") ?: "";
        $holding_date = $request->input("holding_date") ?: "";
        $job_no = $request->input("job_no") ?: "";
        $job_date = $request->input("job_date") ?: "";
        $awb_no = $request->input("awb_no") ?: "";
        $description = $request->input("description") ?: "";
        $unit_id = $request->input("unit_id") ?: 0;
        $quantity = $request->input("quantity") ?: "";
        $transport_type = $request->input("transport_type") ?: "";
        $vehicle_reg_no = $request->input("vehicle_reg_no") ?: "";
        $driver_name = $request->input("driver_name") ?: "";
        $from_place = $request->input("from_place") ?: "";
        $to_place = $request->input("to_place") ?: "";
        $particulars_charges = $request->input("particulars_charges") ?: "";
        $total_amount = $request->input("total_amount") ?: 0;
        $paid_amount = $request->input("paid_amount") ?: 0;
        $due_amount = $request->input("due_amount") ?: 0;
        $full_paid = (((float) $total_amount > 0)) ? (((float) $due_amount > 0) ? 0 : 1) : 0;
        $CreatedAt = date("Y-m-d H:i:s", time());

        $save_transport = transport_bill_list::where('bill_id', '=', $bill_id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($save_transport->exists()) {

            //Update Transport_bill_list
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                //if(full_paid status is not pending but paid) msg("you are not allowed to update this")
                $get_status = transport_bill_list::where("bill_id", "=", $bill_id)->first();

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
                        $save_transport->update([
                            'user_id' => $user_id,
                            'bill_code' => $bill_code,
                            'client_id' => $client_id,
                            'delivery_challan_no' => $delivery_challan_no,
                            'delivery_challan_date' => $delivery_challan_date,
                            'transport_challan_no' => $transport_challan_no,
                            'transport_challan_date' => $transport_challan_date,                          
                            'lc_no' => $lc_no,
                            'lc_date' => $lc_date,
                            'be_no' => $be_no,
                            'be_date' => $be_date,
                            'holding_no' => $holding_no,
                            'holding_date' => $holding_date,
                            'job_no' => $job_no,
                            'job_date' => $job_date,
                            'awb_no' => $awb_no,
                            'description' => $description,
                            'unit_id' => $unit_id,
                            'quantity' => $quantity,
                            'transport_type' => $transport_type,
                            'vehicle_reg_no' => $vehicle_reg_no,
                            'driver_name' => $driver_name,
                            'from_place' => $from_place,
                            'to_place' => $to_place,
                            'particulars_charges' => $particulars_charges,
                            'total_amount' => $total_amount,
                            'paid_amount' => $paid_amount,
                            'due_amount' => $due_amount,
                            'full_paid' => $full_paid,
                            'bill_dd' => $bill_dd,
                            'bill_mm' => $bill_mm,
                            'bill_yyyy' => $bill_yyyy,
                            'bill_date' => $bill_date,
                            'bill_timestamp' => $bill_timestamp,
                            'UpdatedBy' => $this->AUTH_USER->id,
                            //'UpdatedAt' => $CreatedAt,
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Transport bill updated successfully!',
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
                    transport_bill_list::Insert([
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        // 'bill_id' => $bill_id,
                        'user_id' => $user_id,
                        'bill_code' => $bill_code,
                        'client_id' => $client_id,
                        'delivery_challan_no' => $delivery_challan_no,
                        'delivery_challan_date' => $delivery_challan_date,
                        'transport_challan_no' => $transport_challan_no,
                        'transport_challan_date' => $transport_challan_date,                          
                        'lc_no' => $lc_no,
                        'lc_date' => $lc_date,
                        'be_no' => $be_no,
                        'be_date' => $be_date,
                        'holding_no' => $holding_no,
                        'holding_date' => $holding_date,
                        'job_no' => $job_no,
                        'job_date' => $job_date,
                        'awb_no' => $awb_no,
                        'description' => $description,
                        'unit_id' => $unit_id,
                        'quantity' => $quantity,
                        'transport_type' => $transport_type,
                        'vehicle_reg_no' => $vehicle_reg_no,
                        'driver_name' => $driver_name,
                        'from_place' => $from_place,
                        'to_place' => $to_place,
                        'particulars_charges' => $particulars_charges,
                        'total_amount' => $total_amount,
                        'paid_amount' => $paid_amount,
                        'due_amount' => $due_amount,
                        'full_paid' => $full_paid,
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
                        'message' => 'Transport Bill created successfully!',
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

    public function GetSingleTransport(Request $request)
    {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $bill_id = $request->input("bill_id");
            $bill_code = $request->input("bill_code");

            $Transport = transport_bill_list::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID)
                ->where('IsDeleted', '=', 0);

            if($bill_id != '') {
                $Transport->where('bill_id', '=', $bill_id);
            } elseif($bill_code != '') {
                $Transport->where('bill_code', '=', $bill_code);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a Transport Bill id or code',
                ]);
            }

            if(!$Transport->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Transport Bill was not found!',
                ]);
            }

            $Transport = $Transport->first();

            /**
             * Flags set to boolean for javascript
             */
            $Transport->Transport = $Transport->Transport == 1;
            $Transport->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Transport Bill details got successfully!',
                'transport' => $Transport,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    public function DeleteTransport(Request $request)
    {
        //ok
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

        $Transport = transport_bill_list::where("bill_id", "=", $id)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$Transport->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested Transport bill was not found!',
            ]);
        }
        

        try {
            //if(not pending) msg("you are not allowed to delete this")
            $get_status = transport_bill_list::where("bill_id", "=", $id)->first();
            if($get_status->full_paid == 1)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'This bill is closed!',
                ]);
            }

            else if ($trash_mode === 1) {

                $Transport->delete();

            }

            else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $Transport->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Transport Bill deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }


    }

    public function RestoreTransport(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $id = $request->input("bill_id");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $transport = transport_bill_list::where("bill_id", "=", $id)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$transport->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested transport bill was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["updated_at"] = $CreatedAt;
                $transport->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'transport bill restored successfully!',
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

    public function GetUnitDropDown(Request $request)
    {

       try {

            $unit_dropdown = DB::table('units')->select('units.Name')
            ->orderBy('units.id')->get();

           return response()->json([
               'success' => true,
               'message' => 'unit dropdown List got successfully!',
               'unit_dropdown_list' => $unit_dropdown,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetTransportTypeDropDown(Request $request)
    {

       try {

            $transport_type_dropdown = Vehicle::where('company_id',$this->AUTH_USER->CompanyID)
            ->select('vehicle_name','id')
            ->orderBy('id')->get();

           return response()->json([
               'success' => true,
               'message' => 'transport type dropdown List got successfully!',
               'transport_type_dropdown_list' => $transport_type_dropdown,
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

    public function GetLocationDropDown(Request $request)
    {

       try {

            $location_dropdown = Location::where('company_id',$this->AUTH_USER->CompanyID)
            ->select('location_name','id')
            ->orderBy('id')->get();

           return response()->json([
               'success' => true,
               'message' => 'location dropdown List got successfully!',
               'location_dropdown_list' => $location_dropdown,
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

    public function DeleteUnitDropDown(Request $request)
    {
        $id = $request->input('id');

        $delete_unit_dropdown = Unit::where("id", "=", $id);

        if (!$delete_unit_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested unit_list bill was not found!',
            ]);
        }

        try {           
            $delete_unit_dropdown->delete();

            $unit_dropdown = DB::table('units')->select('units.Name')
                ->orderBy('units.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'unit list deleted successfully!',
                'unit' => $unit_dropdown
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }
    }

    public function DeleteTransportTypeDropDown(Request $request)
    {
        $id = $request->input('id');

        $delete_transport_type_dropdown = Vehicle::where("id", "=", $id);

        if (!$delete_transport_type_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested transport_type_list bill was not found!',
            ]);
        }

        try {           
            $delete_transport_type_dropdown->delete();

            $transport_type_dropdown = DB::table('vehicles')->select('vehicles.vehicle_name','vehicles.id')
                ->orderBy('vehicles.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'transport type list deleted successfully!',
                'transport_type' => $transport_type_dropdown
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

    public function DeleteLocationDropDown(Request $request)
    {
        $id = $request->input('id');

        $delete_location_dropdown = Location::where("id", "=", $id);

        if (!$delete_location_dropdown->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested location was not found!',
            ]);
        }

        try {           
            $delete_location_dropdown->delete();

            $location_dropdown = DB::table('locations')->select('locations.location_name','locations.id')
                ->orderBy('locations.id')->get();

            return response()->json([
                'success' => true,
                'message' => 'location deleted successfully!',
                'location' => $location_dropdown
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
     * Add Dropdown APIs
     */

    public function AddTransportTypeDropDown(Request $request)
    {
        $vehicle_name = $request->input("vehicle_name");
        try{
            Vehicle::Insert([
                'vehicle_name' => $vehicle_name,
                'company_id' => $this->AUTH_USER->CompanyID
            ]);

            $vehicle_dropdown = Vehicle::where('company_id',$this->AUTH_USER->CompanyID)
            ->select('vehicle_name','id')
            ->orderBy('id')->get();

            return response()->json([
                'success' => true,
                'message' => 'new vehicle created successfully!',
                'vehicle_dropdown' => $vehicle_dropdown

            ]);
        }catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to create! ' . $exception->getMessage(),
            ]);
        }

    }

    public function AddLocationDropDown(Request $request)
    {
        $location_name = $request->input("location_name");
        try{
            Location::Insert([
                'location_name' => $location_name,
                'company_id' => $this->AUTH_USER->CompanyID
            ]);

            $location_dropdown = Location::where('company_id',$this->AUTH_USER->CompanyID)
            ->select('location_name','id')
            ->orderBy('id')->get();

            return response()->json([
                'success' => true,
                'message' => 'new location created successfully!',
                'location_dropdown' => $location_dropdown

            ]);
        }catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to create! ' . $exception->getMessage(),
            ]);
        }

    }

    
   
}
