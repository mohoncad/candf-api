<?php

namespace App\Http\Controllers\API\Company;

use App\Helper\Helper;
use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Models\Company\Company;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator,Redirect,Response,File;
use DateTime;
use \Exception;
use Storage;

class CompanyInfoController extends Controller
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
            $this->MODULE_CODE = UAP::$ModuleCodes['15'];
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

    public function getCompanyDetails(Request $request)
    {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $id = $request->input("id");

            if($id != '') {
                $Company = Company::where('id', '=', $id);
            } 
            else 
            {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a company id',
                ]);
            }

            if(!$Company->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Company not found!',
                ]);
            }

            $Company = $Company->first();
            $Company->Logo = config('url_config.app_url') . '/company/' . $Company->Logo;

            return response()->json([
                'success' => true,
                'message' => 'Company info details got successfully!',
                'company' => $Company            
                ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    public function SaveCompanyInfo(Request $request)
    {
        $id = $request->input("id");
        $Name = $request->input("Name");
        $Size = $request->input("Size");
        $Address1 = $request->input("Address1");
        $Email = $request->input("Email");
        $ContactNumber = $request->input("ContactNumber");
        $WebAddress = $request->input("WebAddress");
        $Logo = Helper::uploadInPublic($request, 'Logo', 'company/');
        $CreatedAt = date("Y-m-d H:i:s", time());

        $save_company_info = Company::where('id', '=', $id);

        if($save_company_info->exists()) {

            //Update Company Info
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {               
                try {
                    //IsInvoiceNumberTypeSelected
                    $save_company_info = $save_company_info->first();

                    if(! $save_company_info->IsInvoiceNumberTypeSelected)
                    {
                        $save_company_info->InvoiceNumberType = $request->input('InvoiceNumberType');
                        $save_company_info->IsInvoiceNumberTypeSelected = 1;
                    }

                    $save_company_info->Name = $Name;
                    $save_company_info->Size = $Size;
                    $save_company_info->Address1 = $Address1;
                    $save_company_info->Email = $Email;
                    $save_company_info->ContactNumber = $ContactNumber;
                    $save_company_info->WebAddress = $WebAddress;
                    $save_company_info->UpdatedBy =  $this->AUTH_USER->id;
                    $save_company_info->Logo = $Logo;
                    $save_company_info->UpdatedAt = $CreatedAt;
                    $save_company_info->save();

                    return response()->json([
                        'success' => true,
                        'message' => 'Company Info updated successfully!',
                    ]);
                }
                catch (Exception $exception) {
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

            //Insert a new Company Info
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                try {
                    Company::Insert([
                        'Name' => $Name,
                        'Size' => $Size,
                        'Address1' => $Address1,
                        'Email' => $Email,
                        'ContactNumber' => $ContactNumber,
                        'WebAddress' => $WebAddress,
                        'Logo' => $Logo,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Company Info created successfully!',
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


    public function DeleteCompanyInfoLogo(Request $request)
    {

        $id = $request->input("id");
        $company_info_logo = Company::where("id", "=", $id)->first();   
        $logo =  'company/' . $company_info_logo->Logo;
        // dd($logo);
        try{
            if(File::exists(public_path($logo)))
            {
                File::delete(public_path($logo));
            }

            $company_info_logo->update(["Logo" => NULL]);
            return response()->json([
                'success' => true,
                'logo' => 'Logo deleted successfully!',
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
