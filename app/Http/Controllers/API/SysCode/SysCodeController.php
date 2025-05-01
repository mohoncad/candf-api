<?php


namespace App\Http\Controllers\API\SysCode;


use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use App\Models\SysCode\SysCode;
use Illuminate\Support\Facades\DB;

class SysCodeController extends Controller
{
    /**
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private $AUTH_USER;
    const client = "CLIENT";

    public function __construct()
    {
        $this->AUTH_USER = auth()->user();
    }

    public function generateNumberWithLeadingZeros($number, $zeroes_length)
    {
        $N_I_LENGTH = strlen($number);
        $NewCode = $this->GenerateZeroes((int) $zeroes_length - (int) $N_I_LENGTH) . '' . $number;

        return $NewCode;
    }

    public function GenerateZeroes($Length) {
        $Length = intval($Length);
        $Zeroes = '';

        for($i = 1; $i <= $Length; $i++) {
            $Zeroes .= '0';
        }

        return $Zeroes;
    }

    public function GenerateNewCode($ModuleCode, $ZeroesLength)
    {

        $LastIncrement = 0;
        $Prefix = '';
        $NewIncrement = '';
        $NewCode = '';
        $SysCode = SysCode::where('CompanyID', '=', $this->AUTH_USER->CompanyID)->where('ModuleCode', '=', $ModuleCode);

        if ($SysCode->exists()) {
            $SysCode = $SysCode->first();
            $LastIncrement = (int) $SysCode->LastIncrement;
            $Prefix = $SysCode->CodePrefix;
        }

        $NewIncrement = $LastIncrement + 1;

        $N_I_LENGTH = strlen($NewIncrement);
        $NewCode = $Prefix . '' . $this->GenerateZeroes((int) $ZeroesLength - (int) $N_I_LENGTH) . '' . $NewIncrement;


        return $NewCode;
    }

    public function UpdateIncrement($ModuleCode)
    {

        $LastIncrement = 0;
        $NewIncrement = 0;
        $SysCode = SysCode::where('CompanyID', '=', $this->AUTH_USER->CompanyID)->where('ModuleCode', '=', $ModuleCode);

        if ($SysCode->exists()) {
            $__SysCode = $SysCode->first();

            $NewIncrement = (int) $__SysCode->LastIncrement + 1;

            $SysCode->update([
                'LastIncrement' => $NewIncrement,
            ]);

            return true;
        }


        $NewIncrement = $LastIncrement + 1;

        SysCode::Insert([
            'CompanyID' => $this->AUTH_USER->CompanyID,
            'ModuleCode' => $ModuleCode,
            'LastIncrement' => $NewIncrement,
            'CodePrefix' => '',
        ]);

        return true;

    }

    public function getNewBillInvoiceNumber($id, $type,$module, $zeroes_length=5, $bill_type=null)
    {
        $bill_code_number = 0;

        if($type == "CLIENT")
        {
            if ($module == "IMPORT")
            {
                $bill_code_number = Client::select('current_import_bill_code_number')->find($id)->current_import_bill_code_number;
            }
            else if ($module == "EXPORT")
            {
                $bill_code_number = Client::select('current_export_bill_code_number')->find($id)->current_export_bill_code_number;
            }
            else if ($module == "TRANSPORT")
            {
                $bill_code_number = Client::select('current_transport_bill_code_number')->find($id)->current_transport_bill_code_number;
            }
            else if ($module == "BILL_SUMMARY" && !empty($bill_type))
            {
                if($bill_type == "Import")
                {
                    $bill_code_number = Client::select('current_import_summary_bill_code_number')->find($id)->current_import_summary_bill_code_number;
                }
                else if($bill_type == "Export")
                {
                    $bill_code_number = Client::select('current_export_summary_bill_code_number')->find($id)->current_export_summary_bill_code_number;
                }
                else if($bill_type == "Transport")
                {
                    $bill_code_number = Client::select('current_transport_summary_bill_code_number')->find($id)->current_transport_summary_bill_code_number;
                }
            }
            else if ($module == "BILL_PAYMENT")
            {
                $bill_code_number = Client::select('current_payment_bill_code_number')->find($id)->current_payment_bill_code_number;
            }
            return $this->generateNumberWithLeadingZeros( $bill_code_number + 1 , $zeroes_length);
        }

    }

    public function UpdateBillInvoiceNumber($id, $type, $module, $bill_type = null)
    {
        if($type == "CLIENT")
        {

            if ($module == "IMPORT")
            {
                Client::where('id', $id)->update([
                    'current_import_bill_code_number' => DB::raw('current_import_bill_code_number + 1')
                    ]);
            }
            else if ($module == "EXPORT")
            {
                Client::where('id', $id)->update([
                    'current_export_bill_code_number' => DB::raw('current_export_bill_code_number + 1')
                    ]);            
            }      
            else if ($module == "TRANSPORT")
            {
                Client::where('id', $id)->update([
                    'current_transport_bill_code_number' => DB::raw('current_transport_bill_code_number + 1')
                    ]);            
            }
            else if ($module == "BILL_SUMMARY" && !empty($bill_type))
            {
                if($bill_type == "Import")
                {
                    Client::where('id', $id)->update([
                        'current_import_summary_bill_code_number' => DB::raw('current_import_summary_bill_code_number + 1')
                        ]);
                }
                else if($bill_type == "Export")
                {
                    Client::where('id', $id)->update([
                        'current_export_summary_bill_code_number' => DB::raw('current_export_summary_bill_code_number + 1')
                        ]);
                }
                else if($bill_type == "Transport")
                {
                    Client::where('id', $id)->update([
                        'current_transport_summary_bill_code_number' => DB::raw('current_transport_summary_bill_code_number + 1')
                        ]);
                }
         
            }
            else if ($module == "BILL_PAYMENT")
            {
                Client::where('id', $id)->update([
                    'current_payment_bill_code_number' => DB::raw('current_payment_bill_code_number + 1')
                    ]);            
            }
         
        }

        return true;
    }

}
