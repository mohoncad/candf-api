<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\Company\CompanyLicenseController as CompanyLicense;
use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\Company\Company;
use App\User;
use App\UserBranch\UserBranch;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function __construct()
    {
        //
    }

    public function Login(Request $request)
    {
        $credentials = null;
        $token = null;

        $credentials = $request->only(['email', 'password']);
        $email = $request->input('email');

        if (!$token = auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'Your email or password was incorrect!',
            ]);
        }


        $user = User::where('email', '=', $email)->first();

        if ($user->EmailVerified == 0) {
            return response()->json([
                'success' => false,
                'error_code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Your email was not verified! Please check your email for the verification link!',
            ]);
        }

        if($user->IsDeleted == 1) {
            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_DELETED',
                'message' => 'Your account has been deleted!',
            ]);
        }

        $CompanyLicense = CompanyLicense::check($user->CompanyID);

        if ($CompanyLicense->LicenseStatus == "Banned" || $user->IsActive == 0) {

            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_BANNED',
                'message' => 'Your have been banned accessing your account!',
            ]);

        } else if ($CompanyLicense->LicenseStatus == 'Demo Expired') {

            return response()->json([
                'success' => false,
                'error_code' => 'LICENSE_DEMO_EXPIRED',
                'message' => 'Your demo has been expired!',
            ]);

        } else if ($CompanyLicense->LicenseStatus == 'Expired') {

            return response()->json([
                'success' => false,
                'error_code' => 'LICENSE_EXPIRED',
                'message' => 'Your license has been expired!',
            ]);

        }



        //Reset the Branch ID
        User::where('email', '=', $email)->where('IsSupportUser', '=', 0)->update([
            'BranchID' => 0,
        ]);



        /**
         * Successful Login
         */
        return response()->json([
            'success' => true,
            'message' => 'Login successful!',
            'token' => $token
        ], 200);
    }

    public function SupportLogin(Request $request)
    {
        $credentials = null;
        $token = null;

        $credentials = $request->only(['email', 'password']);
        $company_id = $request->input('company_id');
        $email = $request->input('email');

        if (!$token = auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'Your email or password was incorrect!',
            ]);
        }


        $user = User::where('email', '=', $email)->first();

        if ($user->IsSupportUser != 1) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_A_SUPPORT_USER',
                'message' => 'You are not a support user!',
            ]);
        }

        if ($user->IsActive == 0) {
            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_BANNED',
                'message' => 'Your have been banned accessing your account!',
            ]);
        }

        if($user->IsDeleted == 1) {
            return response()->json([
                'success' => false,
                'error_code' => 'ACCOUNT_DELETED',
                'message' => 'Your account has been deleted!',
            ]);
        }


        /**
         * Try to switch to the selected company by updating the company id
         */
        $Company = Company::where('id', '=', $company_id);

        if($Company->exists()) {

            User::where('email', '=', $email)->where('IsSupportUser', '=', 1)->update([
                'CompanyId' => $company_id,
            ]);

        } else {

            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_COMPANY_ID',
                'message' => 'Login failed! Company not found!',
            ], 200);

        }


        //Reset the Branch ID
        User::where('email', '=', $email)->where('IsSupportUser', '=', 1)->update([
            'BranchID' => 0,
        ]);

        /**
         * Successful Login
         */
        return response()->json([
            'success' => true,
            'message' => 'Login successful!',
            'token' => $token
        ], 200);
    }

    public function Logout()
    {
        try {
            auth()->invalidate();
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'message' => 'Logout Failed! ' . $exception->getMessage(),
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful!',
        ], 200);
    }

    public function RefreshToken()
    {
        $token = null;

        try {
            $token = auth()->refresh();
        } catch (Exception $exception) {
            return false;
        }

        return $token;
    }



    public function BranchLogin(Request $request) {
        $user = auth()->user();
        $BranchID = $request->input("BranchID");

        try {

            if(!$user->IsMasterUser && !$user->IsSupportUser) {
                $UserBranch = UserBranch::where('UserID', '=', $user->id)->where('BranchID', '=', $BranchID);
                if(!$UserBranch->exists()) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'NOT_FOUND',
                        'message' => 'Failed to login the branch! Branch does not match for your company or not found!',
                    ]);
                }
            }

            User::where('id', '=', $user->id)->where('CompanyID', '=', $user->CompanyID)->update([
                'BranchID' => $BranchID,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch Logged in!',
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to login the branch! ' . $exception->getMessage(),
            ]);
        }
    }


    public function AuthUserProfile()
    {
        $user = null;

        try {
            $user = auth()->user();
            $user = $user->makeHidden(['OTP_KEY']);

            if($user->ProfilePhoto != '') {
                $user->ProfilePhoto = env('UPLOADS_CDN') . '/Users/' . $user->ProfilePhoto;
            }

            //current branch name
            $Branch = Branch::where("id", "=", $user->BranchID);
            if ($Branch->exists()) {
                $user->CurrentBranchName = $Branch->first()->Name;
            }
            $company = Company::find($user->CompanyID);


            $BranchListArray = [];

            if($user->IsMasterUser == 1 || $user->IsSupportUser == 1) {

                $BranchListArray = Branch::where('CompanyID', '=', $user->CompanyID)->where("IsDeleted", "=", 0)->get();

            } else {

                $UserBranchList = UserBranch::where('UserID', '=', $user->id)->get();
                foreach ($UserBranchList as $UserBranch) {
                    $title = '';

                    $BranchInfo = Branch::where('id', '=', $UserBranch->BranchID);
                    if($BranchInfo->exists()) {
                        array_push($BranchListArray, $BranchInfo->first());
                    }
                }

            }

            $user->BranchList = $BranchListArray;



        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to get the user profile' . $exception->getMessage(),
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile info got successfully!',
            'user' => $user,
            'company' => $company
        ], 200);
    }
}
