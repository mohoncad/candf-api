<?php


namespace App\Http\Controllers\API\Registration;

use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\SysCode\SysCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\User;
use App\Models\Company\Company;
use \Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    /**
     * @var int
     */
    private $DOC_CODE_ZEROES_LENGTH;
    /**
     * @var string
     */
    private $MODULE_CODE;

    public function __construct()
    {
        $this->DOC_CODE_ZEROES_LENGTH = 3;
        $this->MODULE_CODE = UAP::$ModuleCodes['2'];
    }

    public function GenerateNewCode($CompanyID, $ModuleCode, $ZeroesLength)
    {
        $__SysCode = new SysCodeController();

        $LastIncrement = 0;
        $Prefix = '';
        $NewIncrement = '';
        $NewCode = '';
        $SysCode = SysCode::where('CompanyID', '=', $CompanyID)->where('ModuleCode', '=', $ModuleCode);

        if ($SysCode->exists()) {
            $SysCode = $SysCode->first();
            $LastIncrement = (int)$SysCode->LastIncrement;
            $Prefix = $SysCode->CodePrefix;
        }

        $NewIncrement = $LastIncrement + 1;

        $N_I_LENGTH = strlen($NewIncrement);
        $NewCode = $Prefix . '' . $__SysCode->GenerateZeroes((int)$ZeroesLength - (int)$N_I_LENGTH) . '' . $NewIncrement;


        return $NewCode;
    }

    public function UpdateIncrement($CompanyID, $ModuleCode)
    {

        $LastIncrement = 0;
        $NewIncrement = 0;
        $SysCode = SysCode::where('CompanyID', '=', $CompanyID)->where('ModuleCode', '=', $ModuleCode);

        if ($SysCode->exists()) {
            $__SysCode = $SysCode->first();

            $NewIncrement = (int)$__SysCode->LastIncrement + 1;

            $SysCode->update([
                'LastIncrement' => $NewIncrement,
            ]);

            return true;
        }


        $NewIncrement = $LastIncrement + 1;

        SysCode::Insert([
            'CompanyID' => $CompanyID,
            'ModuleCode' => $ModuleCode,
            'LastIncrement' => $NewIncrement,
            'CodePrefix' => '',
        ]);

        return true;

    }

    private function CreateMainBranch($CompanyID, $Email, $ContactNumber)
    {
        $CreatedAt = date("Y-m-d H:i:s", time());
        $Code = $this->GenerateNewCode($CompanyID, UAP::$ModuleCodes['3'], 3);

        Branch::Insert([
            "Code" => $Code,
            'CompanyID' => $CompanyID,
            'Name' => 'Main Branch',
            'Email' => $Email,
            'ContactNumber' => $ContactNumber,
            'CreatedAt' => $CreatedAt,
        ]);

        $this->UpdateIncrement($CompanyID, UAP::$ModuleCodes['3']);

        return $Code;
    }

    public function Register(Request $request)
    {
        $company_name = $request->input('company_name');
        $company_size = $request->input('company_size');
        $user_full_name = $request->input('full_name');
        $email = $request->input('email');
        $contact_number = $request->input('contact_number');
        $password = $request->input('password');
        $CreatedAt = date("Y-m-d H:i:s", time());
        $OTP_KEY = OtpController::NewOtpKey($email);
        $UserExists = User::where('email', '=', $email)->exists();
        $CompanyExists = Company::where('Email', '=', $email)->exists();
        if (!$UserExists && !$CompanyExists) {
            //try to register the company user and the company
            try {

                $Registration = false;

                $CompanyInsert = Company::Insert([
                    "IsActive" => 1,
                    "Name" => $company_name,
                    "Size" => $company_size,
                    "Email" => $email,
                    "ContactNumber" => $contact_number,
                    "IsDemo" => 1,
                    "CreatedAt" => $CreatedAt
                ]);

                if ($CompanyInsert) {
                    $CompanyID = Company::where('Email', '=', $email)->first()->id;

                    //firstly create a main branch for this company
                    $BranchCode = $this->CreateMainBranch($CompanyID, $email, $contact_number);
                    $CreatedBranch = Branch::where('Code', '=', $BranchCode)->where('CompanyID', '=', $CompanyID)->first();

                    $UserInsert = User::Insert([
                        "Code" => $this->GenerateNewCode($CompanyID, $this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                        "CompanyID" => $CompanyID,
                        "BranchID" => $CreatedBranch->id,
                        "IsMasterUser" => 1,
                        "EmailVerified" => 0,
                        "IsActive" => 1,
                        "UserRoleID" => 1,
                        "FullName" => $user_full_name,
                        "email" => $email,
                        "password" => Hash::make($password),
                        "ContactNumber" => $contact_number,
                        "OTP_KEY" => $OTP_KEY,
                        "CreatedAt" => $CreatedAt
                    ]);

                    $this->UpdateIncrement($CompanyID, $this->MODULE_CODE);

                    if ($UserInsert) {
                        $Registration = true;
                    }
                }


                if ($Registration) {

                    //
                    //
                    //Here send the verification link to the email address provided by the user
                    // $company_otp_key (OTP KEY)
                    //Link Should be like: http://c&f/verify_email?otp_token=OTP_KEY
                    //
                    //
                    try {
                        OtpController::EmailOtpKey($email, $OTP_KEY);
                        return response()->json([
                            'success' => true,
                            'message' => 'Registration Successful!',
                        ], 200);

                    } catch (Exception $exception) {
                        Log::info("mail exception : ".json_encode($exception->getMessage()));
                        Log::info("mail trace : ".json_encode($exception->getTrace()));
                        return response()->json([
                            'success' => false,
                            'error_code' => 'MAIL_ERROR',
                            'message' => 'Registration Successful! But There was a problem sending the verification code! ' . $exception->getMessage(),
                        ], 200);

                    }

                }


            } catch (QueryException $e) {

                return response()->json([
                    'success' => false,
                    'error_code' => 'DB_ERROR',
                    'message' => 'Registration Failed! ' . $e->getMessage(),
                ], 200);


            }


        } else {
            return response()->json([
                'success' => false,
                'error_code' => 'DUPLICATE',
                'message' => 'You have entered a duplicate email address!',
            ], 200);
        }

    }

    public function MakeEmailVerified($OTP_KEY)
    {
        $OutputMsg = (string)null;
        try {
            $User = User::where('OTP_KEY', '=', $OTP_KEY);
            if ($User->exists()) {
                if ($User->first()->EmailVerified == 0) {
                    User::where([
                        ['OTP_KEY', '=', $OTP_KEY],
                        ['EmailVerified', '=', 0]
                    ])->update([
                        "EmailVerified" => 1
                    ]);
                    $OutputMsg = "__success__";
                } else {
                    $OutputMsg = "__already_verified__";
                }
            } else {
                $OutputMsg = "__error__";
            }
        } catch (Exception $exception) {
            $OutputMsg = $exception->getMessage();
        }

        return view('Registration.EmailVerification')->with('verification_result', $OutputMsg);
    }
}
