<?php

namespace App\Http\Middleware\CompanyLicense;

use App\Http\Controllers\API\Company\CompanyLicenseController;
use Closure;

class CompanyLicense
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        /**
         * This middleware is after the login action
         * You must be authenticated
         */

        $user = auth()->user();

        if($user->IsSupportUser != 1) {

            $CompanyLicense = CompanyLicenseController::check($user->CompanyID);

            if($CompanyLicense->LicenseStatus == "Banned") {

                auth()->invalidate();

                return response()->json([
                    'success' => false,
                    'error_code' => 'ACCOUNT_BANNED',
                    'message' => 'Failed! Company or account was banned by admin!',
                ], 401);

            } else if($CompanyLicense->LicenseStatus == 'Demo Expired') {

                auth()->invalidate();

                return response()->json([
                    'success' => false,
                    'error_code' => 'LICENSE_DEMO_EXPIRED',
                    'message' => 'Failed! The demo license has been expired!',
                ], 401);

            } else if($CompanyLicense->LicenseStatus == 'Expired') {

                auth()->invalidate();

                return response()->json([
                    'success' => false,
                    'error_code' => 'LICENSE_EXPIRED',
                    'message' => 'Failed! The license has been expired!',
                ], 401);

            }

        }

        return $next($request);
    }
}
